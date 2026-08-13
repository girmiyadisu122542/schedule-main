<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Automatic class scheduling for one semester.
 *
 * There is no conflict detector here, on purpose. Placement works by proposing
 * a (day, slot, room) triple and letting PostgreSQL's three EXCLUDE constraints
 * be the arbiter: a rejected INSERT means the triple was taken, so the search
 * moves on. That keeps one definition of "clash" — the constraint — instead of
 * a second one in PHP that can drift from it.
 */
class ClassScheduleGeneratorService {

    /**
     * Run automatic class scheduling for a semester.
     *
     * @param int $semesterId
     * @return \App\Models\Schedule\ScheduleGenerationRun|string
     */
    public function generate(int $semesterId) {
        // ---- pre-flight checks (NO writes yet) ----
        $semester = Semester::with('status')->find($semesterId);
        if (!$semester) {
            return 'semester_not_found';
        }

        if ($semester->status?->code === SEMESTER_STATUS_CLOSED) {
            return 'semester_is_closed';
        }

        $typeId = LookupService::getValueByCode(GENERATION_TYPE, GENERATION_TYPE_CLASS, needId: true);
        $runningId = LookupService::getValueByCode(GENERATION_STATUS, GENERATION_STATUS_RUNNING, needId: true);
        $completedId = LookupService::getValueByCode(GENERATION_STATUS, GENERATION_STATUS_COMPLETED, needId: true);
        $failedId = LookupService::getValueByCode(GENERATION_STATUS, GENERATION_STATUS_FAILED, needId: true);
        $draftStatusId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_DRAFT, needId: true);

        if (!$typeId || !$runningId || !$completedId || !$failedId || !$draftStatusId) {
            return 'status_lookup_value_not_found';
        }

        $offerings = $this->approvedOfferings($semesterId);
        if ($offerings->isEmpty()) {
            return 'no_approved_offerings_to_schedule';
        }

        // The run row is committed on its own, before any placement, so the
        // progress UI has something to poll while the rest is still working.
        $run = $this->startRun($semester->id, $typeId, $runningId);

        $startedAt = microtime(true);
        $summary = ['placed' => [], 'unplaced' => [], 'skipped' => []];
        $scheduledCount = 0;
        $unplacedCount = 0;

        try {
            $rooms = $this->availableRooms();

            foreach ($offerings as $offering) {
                // A meeting that has been announced is not the generator's to
                // move — re-running must leave published work alone.
                if ($this->hasCommittedSchedule($offering->id, $draftStatusId)) {
                    $summary['skipped'][] = [
                        'course_offering_id' => $offering->id,
                        'label' => $offering->displayLabel(),
                        'reason' => 'already_published',
                    ];

                    continue;
                }

                // Drafts ARE the generator's to replace. Clearing them is what
                // makes re-running actually regenerate: without it a semester
                // that was generated once could never pick up a changed grid.
                $this->clearDrafts($offering->id, $draftStatusId);

                $placement = $this->placeOffering($offering, $rooms, $run, $draftStatusId);
                $scheduledCount += $placement['placed'];

                if ($placement['placed'] === $placement['requested']) {
                    $summary['placed'][] = [
                        'course_offering_id' => $offering->id,
                        'label' => $offering->displayLabel(),
                        'meetings' => $placement['placed'],
                    ];

                    continue;
                }

                // Partly placed still counts as unplaced: the offering does not
                // yet have the weekly load its course declares.
                $unplacedCount++;
                $summary['unplaced'][] = [
                    'course_offering_id' => $offering->id,
                    'label' => $offering->displayLabel(),
                    'requested' => $placement['requested'],
                    'placed' => $placement['placed'],
                    'reason' => $placement['reason'],
                ];
            }
        } catch (\Throwable $exception) {
            $this->finishRun($run, $failedId, $scheduledCount, $unplacedCount, $summary, $startedAt);

            throw $exception;
        }

        $this->finishRun($run, $completedId, $scheduledCount, $unplacedCount, $summary, $startedAt);

        return $run->refresh();
    }

    /**
     * The offerings a timetable may be built from: registrar-approved, for this
     * semester.
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
            // `program` and `section.program` carry the study mode the
            // generation grid is chosen by.
            ->with(['course', 'program', 'section.program', 'instructor'])
            ->where('semester_id', $semesterId)
            ->where('status_lookup_value_id', $approvedId)
            ->orderByDesc('expected_students')
            ->get();
    }

    /**
     * Every room a class may be placed in, largest last so the search takes the
     * smallest room that fits and leaves the halls for the big cohorts.
     *
     * @return \Illuminate\Support\Collection
     */
    private function availableRooms(): Collection {
        return Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->orderBy('capacity')
            ->get();
    }

    /**
     * Whether this offering has meetings the generator must not touch —
     * anything live that is past draft.
     *
     * @param int $offeringId
     * @return bool
     */
    private function hasCommittedSchedule(int $offeringId, int $draftStatusId): bool {
        return ClassSchedule::query()
            ->where('course_offering_id', $offeringId)
            ->where('state', STATE_ACTIVE)
            ->whereNot('status_lookup_value_id', $draftStatusId)
            ->exists();
    }

    /**
     * Discard this offering's draft meetings so they can be re-placed.
     *
     * Only drafts, and only live ones: a cancelled meeting is already out of
     * the way, and a published one was filtered out before we got here.
     *
     * @param int $offeringId
     * @param int $draftStatusId CLASS_SCHEDULE_STATUS value id
     *
     * @return void
     */
    private function clearDrafts(int $offeringId, int $draftStatusId): void {
        ClassSchedule::query()
            ->where('course_offering_id', $offeringId)
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
     * @param int $scheduledCount meetings written
     * @param int $unplacedCount offerings left short
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
     * Place every weekly meeting one offering needs.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param \Illuminate\Support\Collection $rooms candidate rooms, smallest first
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @param int $draftStatusId CLASS_SCHEDULE_STATUS value id
     *
     * @return array{requested: int, placed: int, reason: string|null}
     */
    private function placeOffering(CourseOffering $offering, Collection $rooms, ScheduleGenerationRun $run, int $draftStatusId): array {
        $meetings = $this->meetingsFor($offering);
        $placed = 0;
        $reason = null;
        // One meeting per day reads better than two back-to-back, and it is what
        // a human scheduler would do.
        $usedDays = [];

        foreach ($meetings as $sessionTypeCode) {
            $result = $this->placeMeeting($offering, $sessionTypeCode, $rooms, $run, $draftStatusId, $usedDays);

            if ($result['day'] === null) {
                $reason ??= $result['reason'];

                continue;
            }

            $usedDays[] = $result['day'];
            $placed++;
        }

        return ['requested' => count($meetings), 'placed' => $placed, 'reason' => $reason];
    }

    /**
     * The weekly meetings one offering needs, as SESSION_TYPE codes.
     *
     * The course's declared weekly load drives it: a course with lab hours gets
     * a lab meeting, one with tutorial hours gets a tutorial, and the remaining
     * sessions are lectures.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return array<int, string>
     */
    private function meetingsFor(CourseOffering $offering): array {
        $course = $offering->course;

        $sessions = (int) ($course?->sessions_per_week ?: ScheduleConstant::DEFAULT_SESSIONS_PER_WEEK);
        $sessions = max(1, min($sessions, ScheduleConstant::MAX_SESSIONS_PER_WEEK));

        $meetings = [];
        if ((float) ($course?->lab_hours_per_week ?? 0) > 0) {
            $meetings[] = SESSION_TYPE_LAB;
        }

        if ((float) ($course?->tutorial_hours_per_week ?? 0) > 0) {
            $meetings[] = SESSION_TYPE_TUTORIAL;
        }

        // Whatever is left over is lecture time — and there is always at least
        // one lecture, even for a course that is mostly lab work.
        $lectures = max(1, $sessions - count($meetings));
        $meetings = array_merge(array_fill(0, $lectures, SESSION_TYPE_LECTURE), $meetings);

        return array_slice($meetings, 0, ScheduleConstant::MAX_SESSIONS_PER_WEEK);
    }

    /**
     * Find a free (day, slot, room) for one meeting and write it.
     *
     * The write IS the search: each candidate is attempted in its own
     * transaction, and an EXCLUDE rejection just means "taken, try the next".
     * A per-attempt transaction is required — in PostgreSQL a failed statement
     * poisons the whole transaction it ran in.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param string $sessionTypeCode a SESSION_TYPE code
     * @param \Illuminate\Support\Collection $rooms candidate rooms, smallest first
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @param int $draftStatusId CLASS_SCHEDULE_STATUS value id
     * @param array<int, int> $usedDays days this offering already meets on
     *
     * @return array{day: int|null, reason: string|null}
     */
    private function placeMeeting(CourseOffering $offering, string $sessionTypeCode, Collection $rooms, ScheduleGenerationRun $run, int $draftStatusId, array $usedDays): array {
        $sessionTypeId = LookupService::getValueByCode(SESSION_TYPE, $sessionTypeCode, needId: true);
        $candidateRooms = $this->roomsFor($offering, $sessionTypeCode, $rooms);

        if ($candidateRooms->isEmpty()) {
            return ['day' => null, 'reason' => 'no_room_large_enough'];
        }

        $lastConflict = 'no_free_slot_found';

        // Days this offering does not meet on yet come first; the rest are a
        // fallback for a course with more sessions than there are teaching days.
        // The grid comes from the offering's study mode, so an extension
        // programme is placed at the weekend and a regular one on weekdays.
        $settings = app(ScheduleSettingService::class);
        $setting = $settings->forOffering($offering);
        $teachingDays = $settings->teachingDays($setting);
        $periods = $settings->periods($setting);

        $days = array_merge(
            array_values(array_diff($teachingDays, $usedDays)),
            array_values(array_intersect($teachingDays, $usedDays)),
        );

        foreach ($days as $day) {
            foreach ($periods as $slot) {
                foreach ($candidateRooms as $room) {
                    $attributes = [
                        'course_offering_id' => $offering->id,
                        'semester_id' => $offering->semester_id,
                        'section_id' => $offering->section_id,
                        'instructor_id' => $offering->instructor_id,
                        'room_id' => $room->id,
                        'session_type_lookup_value_id' => $sessionTypeId,
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'status_lookup_value_id' => $draftStatusId,
                        'state' => STATE_ACTIVE,
                        'generation_run_id' => $run->id,
                        'created_by_id' => Auth::id(),
                    ];

                    try {
                        DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

                        ClassSchedule::create($attributes);

                        DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
                    } catch (QueryException $exception) {
                        DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                        $conflict = ClassScheduleService::conflictKey($exception);
                        if (!$conflict) {
                            throw $exception;
                        }

                        // A section or instructor clash rules out this whole
                        // slot, whatever room we try next — but say so only if
                        // nothing better turns up.
                        $lastConflict = $conflict;

                        continue;
                    }

                    return ['day' => $day, 'reason' => null];
                }
            }
        }

        return ['day' => null, 'reason' => $lastConflict];
    }

    /**
     * The rooms this meeting could use, in preference order.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param string $sessionTypeCode a SESSION_TYPE code
     * @param \Illuminate\Support\Collection $rooms candidate rooms, smallest first
     *
     * @return \Illuminate\Support\Collection
     */
    private function roomsFor(CourseOffering $offering, string $sessionTypeCode, Collection $rooms): Collection {
        $fitting = $rooms->filter(fn (Room $room): bool => $room->capacity >= $offering->expected_students);

        if ($sessionTypeCode !== SESSION_TYPE_LAB) {
            return $fitting->values();
        }

        // A lab needs a lab. If the campus has none big enough, any fitting room
        // beats leaving the meeting unplaced.
        $labs = $fitting->filter(fn (Room $room): bool => $room->roomType?->code === ROOM_TYPE_LAB);

        return $labs->isNotEmpty() ? $labs->values() : $fitting->values();
    }
}
