<?php

namespace App\Services\Schedule;

use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use Illuminate\Support\Collection;

/**
 * Where an unplaced offering WOULD fit (C24).
 *
 * Until now a failed placement produced a reason code — `no_free_slot_found`,
 * `cs_no_room_clash` — and left the user to work out the fix by hand. Naming
 * the problem is not the same as solving it, and guided resolution is most of
 * why institutions buy this kind of software rather than building it.
 *
 * This walks the same candidate space the generator does, in the same order of
 * preference, but READ-ONLY: it asks the database whether each slot is free
 * instead of trying to take it. That is a deliberate difference. The generator
 * must write, because only the EXCLUDE constraints can settle a race; a
 * suggestion is advice, and advice that locked rows while it was being
 * calculated would be worse than none.
 *
 * The consequence is that a suggestion can go stale between being shown and
 * being taken. That is fine and expected — acting on it goes through the normal
 * service, where the constraints have the final word.
 */
class PlacementSuggestionService {

    /** More than a handful of options is a list to read, not an answer. */
    private const MAX_SUGGESTIONS = 5;

    /**
     * Free slots for one class offering, best first.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param int $limit
     *
     * @return array<int, array<string, mixed>>
     */
    public function forClassOffering(CourseOffering $offering, int $limit = self::MAX_SUGGESTIONS): array {
        $settings = app(ScheduleSettingService::class);
        $setting = $settings->forOffering($offering);
        $weights = $settings->weights($setting);
        $scorer = app(PlacementScorer::class);
        $workload = app(InstructorWorkloadService::class);

        $semesterId = (int) $offering->semester_id;
        $sectionId = $offering->section_id ? (int) $offering->section_id : null;
        $seatsNeeded = $offering->totalExpectedStudents();

        $rooms = Room::query()
            ->with('building')
            ->where('is_active', true)
            ->where('capacity', '>=', $seatsNeeded)
            ->orderBy('capacity')
            ->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        $attendingIds = array_values(array_filter(array_merge(
            [$sectionId],
            $offering->additionalSections->pluck('section_id')->map(fn ($id): int => (int) $id)->all(),
        )));

        $suggestions = [];

        foreach ($settings->teachingDays($setting) as $day) {
            $sectionDay = $scorer->sectionDay($sectionId, $semesterId, $day);

            foreach ($settings->periods($setting) as $slot) {
                $slotMinutes = (int) round((strtotime($slot['end']) - strtotime($slot['start'])) / 60);

                if (!$workload->canTake($offering->instructor_id, $semesterId, $slotMinutes)) {
                    continue;
                }

                if ($this->classBusy($attendingIds, null, $semesterId, $day, $slot)) {
                    continue;
                }

                if ($offering->instructor_id && $this->classBusy(null, (int) $offering->instructor_id, $semesterId, $day, $slot)) {
                    continue;
                }

                foreach ($rooms as $room) {
                    if ($this->roomBusy((int) $room->id, $semesterId, $day, $slot)) {
                        continue;
                    }

                    if (!$settings->allowsCrossCampusDay($setting) && $scorer->crossesCampus($room, $sectionDay)) {
                        continue;
                    }

                    $suggestions[] = [
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'room_id' => (int) $room->id,
                        'room_code' => $room->code,
                        'room_name' => $room->name__localized,
                        'building' => $room->building?->name__localized,
                        'capacity' => (int) $room->capacity,
                        'score' => $scorer->score($weights, $day, $slot, $room, $seatsNeeded, [], $sectionDay),
                    ];
                }
            }
        }

        usort($suggestions, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Free (date, window, hall) for one exam sitting, best first.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param string $examTypeCode
     * @param array<int, string> $dates the exam period
     * @param array<int, array<string, string>> $windows
     * @param int $limit
     *
     * @return array<int, array<string, mixed>>
     */
    public function forExamOffering(CourseOffering $offering, string $examTypeCode, array $dates, array $windows, int $limit = self::MAX_SUGGESTIONS): array {
        $settings = app(ScheduleSettingService::class);
        $setting = $settings->forOffering($offering);

        $seatsNeeded = $offering->totalExpectedStudents();
        $maxPerDay = $settings->maxExamsPerDay($setting);
        $minGap = $settings->minMinutesBetweenExams($setting);

        $sectionIds = array_values(array_filter(array_merge(
            [$offering->section_id ? (int) $offering->section_id : null],
            $offering->additionalSections->pluck('section_id')->map(fn ($id): int => (int) $id)->all(),
        )));

        $halls = Room::query()
            ->where('is_active', true)
            ->where('is_exam_venue', true)
            ->get()
            ->filter(fn (Room $room): bool => ($room->exam_capacity ?? $room->capacity) >= $seatsNeeded)
            ->sortBy(fn (Room $room): int => (int) ($room->exam_capacity ?? $room->capacity))
            ->values();

        $suggestions = [];

        foreach ($dates as $date) {
            if ($this->examCountOn($sectionIds, $date) >= $maxPerDay) {
                continue;
            }

            foreach ($windows as $slot) {
                if ($minGap > 0 && $this->examTooClose($sectionIds, $date, $slot, $minGap)) {
                    continue;
                }

                foreach ($halls as $hall) {
                    if ($this->hallBusy((int) $hall->id, $date, $slot)) {
                        continue;
                    }

                    $suggestions[] = [
                        'exam_date' => $date,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'room_id' => (int) $hall->id,
                        'room_code' => $hall->code,
                        'room_name' => $hall->name__localized,
                        'capacity' => (int) ($hall->exam_capacity ?? $hall->capacity),
                        // Earliest wins for exams: an exam period is short and
                        // the first free hall on the first free day is what a
                        // registrar wants, not a scored preference.
                        'score' => 0,
                    ];

                    if (count($suggestions) >= $limit) {
                        return $suggestions;
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Whether a cohort or an instructor is already in class in this window.
     *
     * @param array<int, int>|null $sectionIds
     * @param int|null $instructorId
     * @param int $semesterId
     * @param int $day
     * @param array<string, string> $slot
     *
     * @return bool
     */
    private function classBusy(?array $sectionIds, ?int $instructorId, int $semesterId, int $day, array $slot): bool {
        if (empty($sectionIds) && !$instructorId) {
            return false;
        }

        return ClassSchedule::query()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day)
            ->where('state', STATE_ACTIVE)
            ->when($sectionIds, fn ($query) => $query->whereIn('section_id', $sectionIds))
            ->when($instructorId, fn ($query) => $query->where('instructor_id', $instructorId))
            // Overlap, not equality: a 60-minute slot collides with a
            // 90-minute one that merely starts inside it.
            ->where('start_time', '<', $slot['end'])
            ->where('end_time', '>', $slot['start'])
            ->exists();
    }

    /**
     * Whether a room is taken in this window.
     *
     * @param int $roomId
     * @param int $semesterId
     * @param int $day
     * @param array<string, string> $slot
     *
     * @return bool
     */
    private function roomBusy(int $roomId, int $semesterId, int $day, array $slot): bool {
        return ClassSchedule::query()
            ->where('semester_id', $semesterId)
            ->where('room_id', $roomId)
            ->where('day_of_week', $day)
            ->where('state', STATE_ACTIVE)
            ->where('start_time', '<', $slot['end'])
            ->where('end_time', '>', $slot['start'])
            ->exists();
    }

    /**
     * Whether a hall is taken at this date and time.
     *
     * @param int $roomId
     * @param string $date
     * @param array<string, string> $slot
     *
     * @return bool
     */
    private function hallBusy(int $roomId, string $date, array $slot): bool {
        return ExamSchedule::query()
            ->where('room_id', $roomId)
            ->whereDate('exam_date', $date)
            ->where('state', STATE_ACTIVE)
            ->where('start_time', '<', $slot['end'])
            ->where('end_time', '>', $slot['start'])
            ->exists();
    }

    /**
     * How many distinct papers these cohorts already sit on a date.
     *
     * Distinct offerings, not rows: a paper split across three halls is three
     * rows and one exam.
     *
     * @param array<int, int> $sectionIds
     * @param string $date
     *
     * @return int
     */
    private function examCountOn(array $sectionIds, string $date): int {
        if (empty($sectionIds)) {
            return 0;
        }

        return ExamSchedule::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('exam_date', $date)
            ->where('state', STATE_ACTIVE)
            ->distinct()
            ->count('course_offering_id');
    }

    /**
     * Whether this window leaves a cohort too little rest either side.
     *
     * @param array<int, int> $sectionIds
     * @param string $date
     * @param array<string, string> $slot
     * @param int $minGapMinutes
     *
     * @return bool
     */
    private function examTooClose(array $sectionIds, string $date, array $slot, int $minGapMinutes): bool {
        if (empty($sectionIds)) {
            return false;
        }

        $start = strtotime($date . ' ' . $slot['start']);
        $end = strtotime($date . ' ' . $slot['end']);
        $gapSeconds = $minGapMinutes * 60;

        $sameDay = ExamSchedule::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('exam_date', $date)
            ->where('state', STATE_ACTIVE)
            ->get(['start_time', 'end_time']);

        foreach ($sameDay as $existing) {
            $existingStart = strtotime($date . ' ' . $existing->start_time);
            $existingEnd = strtotime($date . ' ' . $existing->end_time);

            $gap = $start >= $existingEnd
                ? $start - $existingEnd
                : ($existingStart >= $end ? $existingStart - $end : 0);

            if ($gap < $gapSeconds) {
                return true;
            }
        }

        return false;
    }
}
