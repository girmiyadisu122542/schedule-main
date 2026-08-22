<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ClassScheduleException;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The three bulk moves a registrar actually makes (C17).
 *
 * Shifting a whole programme by a day, emptying a room that is being
 * refurbished, and cancelling a week for a public holiday were all row-by-row
 * work. Three targeted actions, not a generic batch editor: a batch editor is
 * the classic over-build here, and these three cover what the job needs.
 *
 * Every one reports per-row outcomes rather than aborting on the first refusal.
 * A bulk move across forty sessions will hit a clash somewhere — stopping dead
 * would leave half the change applied and no way to see which half, which is
 * worse than finishing and naming what did not move.
 */
class ScheduleBulkService {

    /** ISO-8601 days in a week, for wrapping a shift. */
    private const DAYS_IN_WEEK = 7;

    /**
     * Move sessions to a different weekday.
     *
     * @param array<int, int> $scheduleIds
     * @param int $shiftDays how many days later; negative moves earlier
     *
     * @return array{moved: int, failed: array<int, array<string, mixed>>}
     */
    public function shiftDays(array $scheduleIds, int $shiftDays): array {
        $moved = 0;
        $failed = [];

        foreach ($this->editable($scheduleIds) as $schedule) {
            // ISO days are 1..7, so the wrap has to be done on 0..6.
            $target = ((((int) $schedule->day_of_week - 1 + $shiftDays) % self::DAYS_IN_WEEK) + self::DAYS_IN_WEEK)
                % self::DAYS_IN_WEEK + 1;

            $outcome = $this->attempt($schedule, ['day_of_week' => $target]);

            if ($outcome === null) {
                $moved++;

                continue;
            }

            $failed[] = ['id' => $schedule->id, 'label' => $schedule->courseOffering?->displayLabel(), 'reason' => $outcome];
        }

        return ['moved' => $moved, 'failed' => $failed];
    }

    /**
     * Move sessions into a different room.
     *
     * The room's capacity is NOT re-checked here on purpose: a registrar
     * emptying a room for refurbishment needs the sessions out of it, and
     * refusing on capacity would leave them in a room that is about to be
     * closed. The EXCLUDE constraint still prevents a double-booking.
     *
     * @param array<int, int> $scheduleIds
     * @param int $roomId
     *
     * @return array{moved: int, failed: array<int, array<string, mixed>>}
     */
    public function swapRoom(array $scheduleIds, int $roomId): array {
        $moved = 0;
        $failed = [];

        foreach ($this->editable($scheduleIds) as $schedule) {
            $outcome = $this->attempt($schedule, ['room_id' => $roomId]);

            if ($outcome === null) {
                $moved++;

                continue;
            }

            $failed[] = ['id' => $schedule->id, 'label' => $schedule->courseOffering?->displayLabel(), 'reason' => $outcome];
        }

        return ['moved' => $moved, 'failed' => $failed];
    }

    /**
     * Cancel every session that falls in a date range — a holiday week.
     *
     * These are per-occurrence exceptions (C18), not cancellations: the weekly
     * rules stay live, so the rooms stay booked for every other week and the
     * timetable comes back by itself afterwards.
     *
     * @param int $semesterId
     * @param string $from
     * @param string $to
     * @param string|null $reason
     *
     * @return array{cancelled: int, dates: int}
     */
    public function cancelDateRange(int $semesterId, string $from, string $to, ?string $reason = null): array {
        $start = strtotime($from);
        $end = strtotime($to);

        if ($start === false || $end === false || $end < $start) {
            return ['cancelled' => 0, 'dates' => 0];
        }

        // Which weekdays the range actually covers — a three-day range needs
        // three of them, not all seven.
        $dates = [];
        for ($day = $start; $day <= $end; $day += 86400) {
            $dates[(int) date('N', $day)][] = date('Y-m-d', $day);
        }

        $cancelled = 0;

        $schedules = ClassSchedule::query()
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            ->whereIn('day_of_week', array_keys($dates))
            ->get();

        foreach ($schedules as $schedule) {
            foreach ($dates[(int) $schedule->day_of_week] ?? [] as $date) {
                $exception = ClassScheduleException::firstOrNew([
                    'class_schedule_id' => $schedule->id,
                    'exception_date' => $date,
                ]);

                // Already cancelled is a no-op, not a second record and not a
                // second count.
                if ($exception->exists) {
                    continue;
                }

                $exception->reason = $reason;
                $exception->created_by_id = Auth::id();
                $exception->save();
                $cancelled++;
            }
        }

        return ['cancelled' => $cancelled, 'dates' => array_sum(array_map('count', $dates))];
    }

    /**
     * The sessions in this set that may still be moved.
     *
     * Published sessions are excluded: people have already been told when to
     * turn up, and moving one silently is exactly the failure a timetable
     * system exists to prevent. Cancelling and rescheduling is the honest path,
     * and it is a per-row decision rather than a bulk one.
     *
     * @param array<int, int> $scheduleIds
     * @return \Illuminate\Support\Collection
     */
    private function editable(array $scheduleIds) {
        $draftId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_DRAFT, needId: true);
        $confirmedId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_CONFIRMED, needId: true);
        $pendingId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_PENDING_CONFIRMATION, needId: true);

        return ClassSchedule::query()
            ->with(['courseOffering.course', 'status'])
            ->whereIn('id', $scheduleIds)
            ->whereIn('status_lookup_value_id', array_filter([$draftId, $confirmedId, $pendingId]))
            ->where('state', STATE_ACTIVE)
            ->get();
    }

    /**
     * Try one change in its own transaction.
     *
     * Per-row, because in PostgreSQL a failed statement poisons the whole
     * transaction it ran in — one clash in a batch of forty would otherwise
     * roll back the thirty-nine that were fine.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @param array<string, mixed> $changes
     *
     * @return string|null null on success, otherwise the conflict key
     */
    private function attempt(ClassSchedule $schedule, array $changes): ?string {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->forceFill($changes);

            // The EXCLUDE constraints used to catch a moved row landing on a
            // taken slot; MySQL cannot express them, so the guard does. The row
            // must not clash with where it is currently sitting.
            $conflict = ScheduleConflictGuard::classSchedule($schedule->getAttributes(), $schedule->id);
            if ($conflict !== null) {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                return $conflict;
            }

            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $conflict = ClassScheduleService::conflictKey($exception);
            if (!$conflict) {
                throw $exception;
            }

            return $conflict;
        }

        return null;
    }
}
