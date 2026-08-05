<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ExamSchedule;
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
     * Run automatic exam scheduling for a semester.
     *
     * @param int $semesterId
     * @param string $examTypeCode which sitting to generate, an EXAM_TYPE code
     *
     * @return \App\Models\Schedule\ScheduleGenerationRun|string
     */
    public function generate(int $semesterId, string $examTypeCode = EXAM_TYPE_FINAL) {
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

        $dates = $this->examDates($semester);
        if (empty($dates)) {
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
                if ($this->alreadyScheduled($offering->id, $examTypeId)) {
                    $summary['skipped'][] = [
                        'course_offering_id' => $offering->id,
                        'label' => $offering->displayLabel(),
                        'reason' => 'already_scheduled',
                    ];

                    continue;
                }

                $placement = $this->placeSitting($offering, $rooms, $dates, $run, $examTypeId, $draftStatusId);

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

            throw $exception;
        }

        $this->finishRun($run, $completedId, $scheduledCount, $unplacedCount, $summary, $startedAt);

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
            ->with(['course', 'section.program'])
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
    private function examDates(Semester $semester): array {
        $end = Carbon::parse($semester->end_date);
        $start = $end->copy()->subDays(ScheduleConstant::EXAM_PERIOD_DAYS);

        // A semester shorter than the exam period still gets one: start no
        // earlier than the semester itself.
        $semesterStart = Carbon::parse($semester->start_date);
        if ($start->lessThan($semesterStart)) {
            $start = $semesterStart->copy();
        }

        $dates = [];
        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            if ($date->dayOfWeekIso === ScheduleConstant::DAY_SUNDAY) {
                continue;
            }

            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * Whether this offering already has a live sitting of this type.
     *
     * @param int $offeringId
     * @param int $examTypeId EXAM_TYPE value id
     *
     * @return bool
     */
    private function alreadyScheduled(int $offeringId, int $examTypeId): bool {
        return ExamSchedule::query()
            ->where('course_offering_id', $offeringId)
            ->where('exam_type_lookup_value_id', $examTypeId)
            ->where('state', STATE_ACTIVE)
            ->exists();
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
    private function placeSitting(CourseOffering $offering, Collection $rooms, array $dates, ScheduleGenerationRun $run, int $examTypeId, int $draftStatusId): array {
        $candidateRooms = $rooms->filter(
            fn (Room $room): bool => ($room->exam_capacity ?? $room->capacity) >= $offering->expected_students
        )->values();

        if ($candidateRooms->isEmpty()) {
            return ['date' => null, 'reason' => 'no_exam_venue_large_enough'];
        }

        $lastConflict = 'no_free_exam_slot_found';

        foreach ($dates as $date) {
            foreach (ScheduleConstant::EXAM_TIME_SLOTS as $slot) {
                foreach ($candidateRooms as $room) {
                    $attributes = [
                        'course_offering_id' => $offering->id,
                        'semester_id' => $offering->semester_id,
                        'section_id' => $offering->section_id,
                        'exam_type_lookup_value_id' => $examTypeId,
                        'exam_date' => $date,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'room_id' => $room->id,
                        'required_invigilators' => ScheduleConstant::DEFAULT_REQUIRED_INVIGILATORS,
                        'status_lookup_value_id' => $draftStatusId,
                        'state' => STATE_ACTIVE,
                        'generation_run_id' => $run->id,
                        'created_by_id' => Auth::id(),
                    ];

                    try {
                        DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

                        ExamSchedule::create($attributes);

                        DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
                    } catch (QueryException $exception) {
                        DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                        $conflict = ExamScheduleService::conflictKey($exception);
                        if (!$conflict) {
                            throw $exception;
                        }

                        // A second sitting of this type is not a placement
                        // problem — no other date or hall will fix it.
                        if ($conflict === 'exam_already_scheduled_for_offering') {
                            return ['date' => null, 'reason' => $conflict];
                        }

                        $lastConflict = $conflict;

                        continue;
                    }

                    return ['date' => $date, 'reason' => null];
                }
            }
        }

        return ['date' => null, 'reason' => $lastConflict];
    }
}
