<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use App\Models\Schedule\ScheduleGenerationRun;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Undo for a generation run (C41).
 *
 * A run's `summary` says what happened; its `snapshot` is what it did. Keeping
 * the rows as data is what makes a regeneration reversible — without it, a run
 * that turns out worse than the one before is unrecoverable, so in practice
 * nobody runs the generator twice.
 *
 * Restoring replays the snapshot through ordinary inserts, so all seven EXCLUDE
 * constraints still apply. A snapshot is a record of what was legal at the
 * time, never a licence to write rows that are not legal now — if the world has
 * moved on, the rows that no longer fit are reported rather than forced.
 */
class ScheduleSnapshotService {

    /** The columns worth keeping. Ids and timestamps are not restorable state. */
    private const CLASS_COLUMNS = [
        'course_offering_id', 'semester_id', 'section_id', 'instructor_id', 'room_id',
        'session_type_lookup_value_id', 'day_of_week', 'start_time', 'end_time',
        'status_lookup_value_id', 'state', 'is_pinned', 'specific_date',
    ];

    private const EXAM_COLUMNS = [
        'course_offering_id', 'semester_id', 'section_id', 'room_id',
        'exam_type_lookup_value_id', 'exam_date', 'start_time', 'end_time',
        'required_invigilators', 'seat_allocation', 'part_number', 'part_count',
        'status_lookup_value_id', 'state', 'is_pinned',
    ];

    /**
     * Record what a class run laid down.
     *
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @return void
     */
    public function captureClassRun(ScheduleGenerationRun $run): void {
        $rows = ClassSchedule::query()
            ->where('generation_run_id', $run->id)
            ->get(self::CLASS_COLUMNS)
            ->map(fn ($row) => $row->only(self::CLASS_COLUMNS))
            ->all();

        $run->forceFill(['snapshot' => ['kind' => 'class', 'rows' => $rows]])->save();
    }

    /**
     * Record what an exam run laid down.
     *
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     * @return void
     */
    public function captureExamRun(ScheduleGenerationRun $run): void {
        $rows = ExamSchedule::query()
            ->where('generation_run_id', $run->id)
            ->get(self::EXAM_COLUMNS)
            ->map(fn ($row) => $row->only(self::EXAM_COLUMNS))
            ->all();

        $run->forceFill(['snapshot' => ['kind' => 'exam', 'rows' => $rows]])->save();
    }

    /**
     * Put a run's rows back.
     *
     * Draft rows for the affected offerings are cleared first, exactly as a
     * regeneration would — restoring is a regeneration whose answer is already
     * known. Published rows are left alone: they are not this run's to replace.
     *
     * @param \App\Models\Schedule\ScheduleGenerationRun $run
     *
     * @return array{restored: int, rejected: int, reasons: array<int, string>}|string
     *         a translation key when the run cannot be restored at all
     */
    public function restore(ScheduleGenerationRun $run): array|string {
        $snapshot = $run->snapshot;
        if (empty($snapshot['rows'] ?? [])) {
            return 'generation_run_has_no_snapshot';
        }

        $isExam = ($snapshot['kind'] ?? 'class') === 'exam';
        $rows = $snapshot['rows'];

        $draftStatusId = $isExam
            ? \App\Services\Lookup\LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_DRAFT, needId: true)
            : \App\Services\Lookup\LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_DRAFT, needId: true);

        $offeringIds = array_values(array_unique(array_column($rows, 'course_offering_id')));

        // Clear the drafts this restore is replacing — but never a pinned row,
        // which is somebody's deliberate hand placement and outranks both the
        // run being undone and the one being put back.
        $query = $isExam ? ExamSchedule::query() : ClassSchedule::query();
        $query->whereIn('course_offering_id', $offeringIds)
            ->where('status_lookup_value_id', $draftStatusId)
            ->where('is_pinned', false)
            ->delete();

        $restored = 0;
        $rejected = 0;
        $reasons = [];

        foreach ($rows as $attributes) {
            $attributes['generation_run_id'] = $run->id;
            $attributes['created_by_id'] = Auth::id();

            try {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

                // The replay used to be policed by the EXCLUDE constraints
                // themselves; MySQL cannot express them, so the guard decides
                // whether the world has moved on since the snapshot was taken.
                $conflict = $isExam
                    ? ScheduleConflictGuard::examSchedule($attributes)
                    : ScheduleConflictGuard::classSchedule($attributes);

                if ($conflict !== null) {
                    DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                    $rejected++;
                    $reasons[$conflict] = true;

                    continue;
                }

                $isExam ? ExamSchedule::create($attributes) : ClassSchedule::create($attributes);

                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
                $restored++;
            } catch (QueryException $exception) {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                $conflict = $isExam
                    ? ExamScheduleService::conflictKey($exception)
                    : ClassScheduleService::conflictKey($exception);

                if (!$conflict) {
                    throw $exception;
                }

                // The world moved on — something else now holds this slot.
                // Reported, never forced.
                $rejected++;
                $reasons[$conflict] = true;
            }
        }

        return [
            'restored' => $restored,
            'rejected' => $rejected,
            'reasons' => array_keys($reasons),
        ];
    }
}
