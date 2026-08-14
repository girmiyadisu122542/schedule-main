<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ExamSchedule;
use App\Models\Schedule\ScheduleSetting;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Automatic exam scheduling for one semester.
 *
 * Same shape as the class generator, and for the same reason: a candidate
 * (date, window, hall) is proposed and PostgreSQL's two EXCLUDE constraints
 * decide whether it holds. There is no conflict detector in PHP.
 */
class ExamScheduleGeneratorService {

    /**
     * Exam dates already worked out this run, keyed by setting id.
     *
     * @var array<string, array<int, string>>
     */
    private array $dateCache = [];

    /**
     * Run automatic exam scheduling for a semester.
     *
     * @param int $semesterId
     * @param string $examTypeCode which sitting to generate, an EXAM_TYPE code
     *
     * @return \App\Models\Schedule\ScheduleGenerationRun|string
     */
    public function generate(int $semesterId, string $examTypeCode = EXAM_TYPE_FINAL, bool $dryRun = false) {
        // ---- pre-flight checks (NO writes yet) ----
        $semester = Semester::with('status')->find($semesterId);
        if (!$semester) {
            return 'semester_not_found';
        }

        if ($semester->status?->code === SEMESTER_STATUS_CLOSED) {
            return 'semester_is_closed';
        }

        $examTypeId = LookupService::getValueByCode(EXAM_TYPE, $examTypeCode, needId: true);
        $typeId = LookupService::getValueByCode(GENERATION_TYPE, GENERATION_TYPE_EXAM, needId: true);
        $runningId = LookupService::getValueByCode(GENERATION_STATUS, GENERATION_STATUS_RUNNING, needId: true);
        $completedId = LookupService::getValueByCode(GENERATION_STATUS, GENERATION_STATUS_COMPLETED, needId: true);
        $failedId = LookupService::getValueByCode(GENERATION_STATUS, GENERATION_STATUS_FAILED, needId: true);
        $draftStatusId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_DRAFT, needId: true);

        if (!$examTypeId || !$typeId || !$runningId || !$completedId || !$failedId || !$draftStatusId) {
            return 'status_lookup_value_not_found';
        }

        $offerings = $this->approvedOfferings($semesterId);
        if ($offerings->isEmpty()) {
            return 'no_approved_offerings_to_schedule';
        }

        // Exam days now come from the study mode, so the date list is resolved
        // per offering rather than once for the semester. This only checks that
        // the semester has an exam period at all.
        if (empty($this->examDates($semester, null))) {
            return 'semester_has_no_exam_period';
        }

        // The run row is committed on its own, before any placement, so the
        // progress UI has something to poll while the rest is still working.
        $run = $this->startRun($semester->id, $typeId, $runningId);

        $startedAt = microtime(true);
        $summary = ['placed' => [], 'unplaced' => [], 'skipped' => []];
        $scheduledCount = 0;
        $unplacedCount = 0;

        try {
            $rooms = $this->examVenues();

            foreach ($offerings as $offering) {
                // The composite unique already forbids a second sitting of the
                // same type, but reporting it as "skipped" beats reporting it
                // as a failure — nothing is wrong.
                if ($this->hasCommittedSitting($offering->id, $examTypeId, $draftStatusId)) {
                    $summary['skipped'][] = [
                        'course_offering_id' => $offering->id,
                        'label' => $offering->displayLabel(),
                        'reason' => 'already_published',
                    ];

                    continue;
                }

                // Drafts are the generator's to replace — this is what lets a
                // changed exam grid or a changed course duration take effect.
                $this->clearDraftSittings($offering->id, $examTypeId, $draftStatusId);

                $placement = $this->placeSitting($offering, $rooms, $semester, $run, $examTypeId, $draftStatusId);

                if ($placement['date'] !== null) {
                    $scheduledCount++;
                    $summary['placed'][] = [
                        'course_offering_id' => $offering->id,
                        'label' => $offering->displayLabel(),
                        'exam_date' => $placement['date'],
                    ];

                    continue;
                }

                $unplacedCount++;
                $summary['unplaced'][] = [
                    'course_offering_id' => $offering->id,
                    'label' => $offering->displayLabel(),
                    'requested' => 1,
                    'placed' => 0,
                    'reason' => $placement['reason'],
                ];
            }
        } catch (\Throwable $exception) {
            $this->finishRun($run, $failedId, $scheduledCount, $unplacedCount, $summary, $startedAt);

            // A rehearsal that fell over still has to leave nothing behind.
            if ($dryRun) {
                ExamSchedule::where('generation_run_id', $run->id)->delete();
            }

            throw $exception;
        }

        $this->finishRun($run, $completedId, $scheduledCount, $unplacedCount, $summary, $startedAt);

        // A rehearsal: the placements really happened, against the real
        // constraints, so the answer is honest — then the rows are removed and
        // the timetable is exactly as it was (C42).
        //
        // The rows do exist for the duration of the run, which is the price of
        // giving a true answer: the only way to know a slot is free is to try
        // to take it. Nothing else may be generating at the same time anyway.
        if ($dryRun) {
            ExamSchedule::where('generation_run_id', $run->id)->delete();
            $run->forceFill(['is_dry_run' => true])->save();

            return $run->refresh();
        }

        // Keep what this run laid down, so it can be put back if the next one
        // turns out worse (C41). After finishRun, so a failed run leaves no
        // snapshot to restore from.
        app(ScheduleSnapshotService::class)->captureExamRun($run);

        return $run->refresh();
    }

    /**
     * The offerings an exam timetable may be built from.
     *
     * Largest cohort first: the big ones have the fewest halls that will take
     * them, so they get first pick.
     *
     * @param int $semesterId
     * @return \Illuminate\Support\Collection
     */
    private function approvedOfferings(int $semesterId): Collection {
        $approvedId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_REGISTRAR_APPROVED, needId: true);
        if (!$approvedId) {
            return collect();
        }

        return CourseOffering::query()
            // `program` / `section.program` carry the study mode the exam grid
            // is chosen by; `course` carries this exam's own length.
            ->with(['course', 'program', 'section.program'])
            ->where('semester_id', $semesterId)
            ->where('status_lookup_value_id', $approvedId)
            ->orderByDesc('expected_students')
            ->get();
    }

    /**
     * The halls a sitting may be placed in, smallest first so a big auditorium
     * is not spent on a cohort of forty.
     *
     * @return \Illuminate\Support\Collection
     */
    private function examVenues(): Collection {
        return Room::query()
            ->where('is_active', true)
            ->where('is_exam_venue', true)
            ->orderByRaw('COALESCE(exam_capacity, capacity)')
            ->get();
    }

    /**
     * The dates the exam period covers: the last stretch of the semester,
     * Sundays left out.
     *
     * @param \App\Models\Academic\Semester $semester
     * @return array<int, string>
     */
    private function examDates(Semester $semester, ?ScheduleSetting $setting, ?string $examTypeCode = null): array {
        $settings = app(ScheduleSettingService::class);
        $examDays = $settings->examDays($setting);

        // Cache per setting AND per exam type: a midterm week and a final week
        // are different calendars out of the same grid.
        $cacheKey = (string) ($setting?->id ?? 'default') . ':' . ($examTypeCode ?? 'any');
        if (array_key_exists($cacheKey, $this->dateCache)) {
            return $this->dateCache[$cacheKey];
        }

        // The window is DECLARED, never inferred. Two levels, both real dates a
        // registrar has published: a per-exam-type override when a midterm week
        // differs from the final week, otherwise the semester's own exam
        // period, which is mandatory and therefore always present.
        //
        // Nothing is derived from `exam_period_days` any more. Counting back
        // from the end of term produced dates nobody had announced, and when it
        // placed nothing there was no way to tell whether the period was wrong
        // or the halls were full.
        $declared = $settings->declaredExamWindow($semester, $examTypeCode);

        $start = Carbon::parse($declared['start'] ?? $semester->exam_start_date);
        $end = Carbon::parse($declared['end'] ?? $semester->exam_end_date);

        $dates = [];
        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            // The configured exam days, not a hardcoded "skip Sunday" — an
            // institution may examine at the weekend, and the weekend intake
            // examines when it is actually on campus.
            if (!in_array($date->dayOfWeekIso, $examDays, true)) {
                continue;
            }

            $dates[] = $date->toDateString();
        }

        return $this->dateCache[$cacheKey] = $dates;
    }

    /**
     * Whether this offering already has a live sitting of this type.
     *
     * @param int $offeringId
     * @param int $examTypeId EXAM_TYPE value id
     *
     * @return bool
     */
    private function hasCommittedSitting(int $offeringId, int $examTypeId, int $draftStatusId): bool {
        return ExamSchedule::query()
            ->where('course_offering_id', $offeringId)
            ->where('exam_type_lookup_value_id', $examTypeId)
            ->where('state', STATE_ACTIVE)
            ->whereNot('status_lookup_value_id', $draftStatusId)
            ->exists();
    }

    /**
     * Discard this offering's draft sittings of one exam type so they can be
     * re-placed.
     *
     * Only drafts: a sitting that has gone to the department for confirmation,
     * or been published, is not the generator's to withdraw.
     *
     * @param int $offeringId
     * @param int $examTypeId EXAM_TYPE value id
     * @param int $draftStatusId EXAM_SCHEDULE_STATUS value id
     *
     * @return void
     */
    private function clearDraftSittings(int $offeringId, int $examTypeId, int $draftStatusId): void {
        ExamSchedule::query()
            ->where('course_offering_id', $offeringId)
            ->where('exam_type_lookup_value_id', $examTypeId)
            ->where('state', STATE_ACTIVE)
            ->where('status_lookup_value_id', $draftStatusId)
            ->delete();
    }

    /**
     * Open a run row in its own transaction.
     *
     * @param int $semesterId
     * @param int $typeId GENERATION_TYPE value id
     * @param int $runningId GENERATION_STATUS value id
     *
     * @return \App\Models\Schedule\ScheduleGenerationRun
     */
    private function startRun(int $semesterId, int $typeId, int $runningId): ScheduleGenerationRun {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $run = ScheduleGenerationRun::create([
                'semester_id' => $semesterId,
                'type_lookup_value_id' => $typeId,
                'status_lookup_value_id' => $runningId,
                'run_by_id' => Auth::id(),
                'started_at' => now(),
            ]);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $run;
    }

    /**
     * Close a run row out with its outcome.
     *
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @param int $statusId GENERATION_STATUS value id
     * @param int $scheduledCount sittings written
     * @param int $unplacedCount offerings left without one
     * @param array $summary per-offering detail for the progress UI
     * @param float $startedAt microtime the placement loop began
     *
     * @return void
     */
    private function finishRun(ScheduleGenerationRun $run, int $statusId, int $scheduledCount, int $unplacedCount, array $summary, float $startedAt): void {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $run->status_lookup_value_id = $statusId;
            $run->scheduled_count = $scheduledCount;
            $run->unplaced_count = $unplacedCount;
            $run->summary = $summary;
            $run->duration_seconds = (int) round(microtime(true) - $startedAt);
            $run->completed_at = now();
            $run->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }
    }

    /**
     * Find a free (date, window, hall) for one sitting and write it.
     *
     * The write IS the search — each candidate is attempted in its own
     * transaction, because in PostgreSQL a failed statement poisons the whole
     * transaction it ran in.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param \Illuminate\Support\Collection $rooms candidate halls, smallest first
     * @param array<int, string> $dates the exam period
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @param int $examTypeId EXAM_TYPE value id
     * @param int $draftStatusId EXAM_SCHEDULE_STATUS value id
     *
     * @return array{date: string|null, reason: string|null}
     */
    private function placeSitting(CourseOffering $offering, Collection $rooms, Semester $semester, ScheduleGenerationRun $run, int $examTypeId, int $draftStatusId): array {
        $settings = app(ScheduleSettingService::class);
        $setting = $settings->forOffering($offering);
        $examTypeCode = LookupService::getValueById($examTypeId)?->code;

        // Everyone who sits this paper, including any cross-listed cohorts.
        $seatsNeeded = $offering->totalExpectedStudents();

        // The grid comes from the offering's study mode; the LENGTH resolves
        // course -> exam type -> the mode's default, so a ninety-minute midterm
        // and a three-hour final get different windows out of the same day.
        $dates = $this->examDates($semester, $setting, $examTypeCode);
        $windows = $settings->examWindows($setting, $settings->examDurationFor($offering, $setting, $examTypeCode));

        if (empty($dates) || empty($windows)) {
            return ['date' => null, 'reason' => 'semester_has_no_exam_period'];
        }

        // Halls that take the whole cohort on their own, smallest first.
        $wholeRooms = $rooms->filter(
            fn (Room $room): bool => $this->seats($room) >= $seatsNeeded
        )->values();

        // No single hall is big enough — the normal case for a large cohort,
        // not an error. The paper is then split across several halls sitting it
        // at the same hour (C9).
        $split = $wholeRooms->isEmpty() ? $this->chooseHalls($rooms, $seatsNeeded) : null;

        if ($wholeRooms->isEmpty() && $split === null) {
            return ['date' => null, 'reason' => 'no_exam_venue_large_enough'];
        }

        $maxPerDay = $settings->maxExamsPerDay($setting);
        $minGapMinutes = $settings->minMinutesBetweenExams($setting);
        $sectionIds = array_values(array_filter(array_merge(
            [$offering->section_id ? (int) $offering->section_id : null],
            $offering->additionalSections->pluck('section_id')->map(fn ($id): int => (int) $id)->all(),
        )));

        $lastConflict = 'no_free_exam_slot_found';

        foreach ($dates as $date) {
            // Three papers in one day is legal under the overlap rule and
            // indefensible to a student. So is a second paper starting as the
            // first one ends (C8).
            if ($this->tooManyExamsOn($sectionIds, $date, $maxPerDay)) {
                $lastConflict = 'cohort_has_too_many_exams_that_day';

                continue;
            }

            foreach ($windows as $slot) {
                if ($minGapMinutes > 0 && $this->tooCloseToAnotherExam($sectionIds, $date, $slot, $minGapMinutes)) {
                    $lastConflict = 'exams_too_close_together';

                    continue;
                }

                // One hall if the cohort fits in one, otherwise the split set.
                $attempts = $split !== null
                    ? [$split]
                    : $wholeRooms->map(fn (Room $room): array => [['room' => $room, 'seats' => $seatsNeeded]])->all();

                foreach ($attempts as $halls) {
                    $written = $this->writeSitting($offering, $halls, $date, $slot, $semester, $run, $examTypeId, $draftStatusId, $setting);

                    if ($written === null) {
                        return ['date' => $date, 'reason' => null];
                    }

                    // A second sitting of this type is not a placement problem
                    // — no other date or hall will fix it.
                    if ($written === 'exam_already_scheduled_for_offering') {
                        return ['date' => null, 'reason' => $written];
                    }

                    $lastConflict = $written;
                }
            }
        }

        return ['date' => null, 'reason' => $lastConflict];
    }

    /**
     * How many seats a hall offers an exam.
     *
     * `exam_capacity` is the spaced-seating figure and is the right number
     * here; `capacity` is the teaching figure and is only the fallback.
     *
     * @param \App\Models\Physical\Room $room
     * @return int
     */
    private function seats(Room $room): int {
        return (int) ($room->exam_capacity ?? $room->capacity);
    }

    /**
     * Pick the fewest halls that between them seat the whole cohort.
     *
     * Largest first, so a 300-strong cohort becomes two halls rather than
     * seven — every extra hall is another set of invigilators and another
     * place for a paper to go missing.
     *
     * @param \Illuminate\Support\Collection $rooms
     * @param int $seatsNeeded
     *
     * @return array<int, array{room: \App\Models\Physical\Room, seats: int}>|null null when even every hall together is too small
     */
    private function chooseHalls(Collection $rooms, int $seatsNeeded): ?array {
        $ordered = $rooms->sortByDesc(fn (Room $room): int => $this->seats($room))->values();

        $chosen = [];
        $remaining = $seatsNeeded;

        foreach ($ordered as $room) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $this->seats($room));
            if ($take <= 0) {
                continue;
            }

            $chosen[] = ['room' => $room, 'seats' => $take];
            $remaining -= $take;
        }

        return $remaining > 0 ? null : $chosen;
    }

    /**
     * Write one sitting — or its several parts — inside a single transaction.
     *
     * All-or-nothing on purpose: half a split sitting is worse than none, since
     * it seats part of the cohort and silently leaves the rest without a hall.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param array<int, array{room: \App\Models\Physical\Room, seats: int}> $halls
     * @param string $date
     * @param array<string, string> $slot
     * @param \App\Models\Academic\Semester $semester
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @param int $examTypeId
     * @param int $draftStatusId
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     *
     * @return string|null null on success, otherwise the conflict key
     */
    private function writeSitting(CourseOffering $offering, array $halls, string $date, array $slot, Semester $semester, ScheduleGenerationRun $run, int $examTypeId, int $draftStatusId, ?ScheduleSetting $setting): ?string {
        $settings = app(ScheduleSettingService::class);
        $partCount = count($halls);

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($halls as $index => $hall) {
                ExamSchedule::create([
                    'course_offering_id' => $offering->id,
                    'semester_id' => $offering->semester_id,
                    // Only the FIRST part carries the cohort.
                    //
                    // `es_no_section_clash` is a partial EXCLUDE — it ignores
                    // rows whose section_id is null. Every part of one paper is
                    // the same cohort at the same hour, so carrying the section
                    // on all of them would make the parts reject each other.
                    // Part 1 holds the section and so still blocks any OTHER
                    // exam for that cohort in the window, which is the whole
                    // point of the constraint; the remaining parts are seat
                    // allocations of a sitting that is already protected.
                    'section_id' => $index === 0 ? $offering->section_id : null,
                    'exam_type_lookup_value_id' => $examTypeId,
                    'exam_date' => $date,
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'room_id' => $hall['room']->id,
                    'seat_allocation' => $hall['seats'],
                    'part_number' => $index + 1,
                    'part_count' => $partCount,
                    // Derived from how many actually sit in THIS hall, not
                    // typed and not copied from the cohort total (C11).
                    'required_invigilators' => $settings->invigilatorsFor($setting, (int) $hall['seats']),
                    'status_lookup_value_id' => $draftStatusId,
                    'state' => STATE_ACTIVE,
                    'generation_run_id' => $run->id,
                    'created_by_id' => Auth::id(),
                ]);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $conflict = ExamScheduleService::conflictKey($exception);
            if (!$conflict) {
                throw $exception;
            }

            return $conflict;
        }

        return null;
    }

    /**
     * Whether any of these cohorts already has its day's worth of exams.
     *
     * @param array<int, int> $sectionIds
     * @param string $date
     * @param int $maxPerDay
     *
     * @return bool
     */
    private function tooManyExamsOn(array $sectionIds, string $date, int $maxPerDay): bool {
        if (empty($sectionIds)) {
            return false;
        }

        // A split sitting is several rows but one paper, so count distinct
        // offerings rather than rows — otherwise a three-hall exam alone looks
        // like three exams and blocks the day.
        $count = ExamSchedule::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('exam_date', $date)
            ->where('state', STATE_ACTIVE)
            ->distinct()
            ->count('course_offering_id');

        return $count >= $maxPerDay;
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
    private function tooCloseToAnotherExam(array $sectionIds, string $date, array $slot, int $minGapMinutes): bool {
        if (empty($sectionIds)) {
            return false;
        }

        $gapSeconds = $minGapMinutes * 60;
        $start = strtotime($date . ' ' . $slot['start']);
        $end = strtotime($date . ' ' . $slot['end']);

        $sameDay = ExamSchedule::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('exam_date', $date)
            ->where('state', STATE_ACTIVE)
            ->get(['start_time', 'end_time']);

        foreach ($sameDay as $existing) {
            $existingStart = strtotime($date . ' ' . $existing->start_time);
            $existingEnd = strtotime($date . ' ' . $existing->end_time);

            // Overlaps are already impossible; what is being measured is the
            // rest between two windows that do not touch.
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
