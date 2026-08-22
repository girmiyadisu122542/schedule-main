<?php

namespace App\Services\Schedule;

use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use Illuminate\Database\Eloquent\Builder;

/**
 * The scheduling conflict engine, in application code.
 *
 * On PostgreSQL these six rules were GiST EXCLUDE constraints — the storage
 * engine itself refused to double-book a room, an instructor, a section or an
 * invigilator, and the services only had to translate the resulting
 * QueryException into a message. MySQL has neither EXCLUDE constraints nor
 * range types, so there is no DDL that says any of this; the rules had to move
 * up a layer.
 *
 * The checks below are the same predicates the constraints carried, spelled as
 * SQL. Two things are worth knowing about the port:
 *
 *  - Every check is a locking read (`FOR UPDATE`) and MUST run inside the
 *    caller's write transaction. Under InnoDB's default REPEATABLE READ that
 *    takes gap locks over the range it scans, so a concurrent insert of a
 *    clashing row blocks rather than slipping between the check and the write.
 *    This is a weaker guarantee than an EXCLUDE constraint — which could not be
 *    bypassed at all — but it holds for every write that goes through these
 *    services.
 *  - `state` still means exactly what it meant: STATE_ACTIVE is the liveness
 *    flag the EXCLUDE predicates filtered on, so cancelling a meeting frees its
 *    slot without deleting the row, unchanged.
 *
 * Overlap is half-open, matching PostgreSQL's default `[)` range bounds: a
 * class ending at 10:00 does not clash with one starting at 10:00.
 */
class ScheduleConflictGuard {

    /**
     * The clash a class meeting would cause, or null when the slot is free.
     *
     * Checked in the order the three EXCLUDE constraints are declared in
     * ClassScheduleService::CONFLICT_KEYS.
     *
     * `specific_date` is deliberately not considered — the EXCLUDE constraints
     * did not consider it either. A one-off makeup class still holds the weekday
     * slot it names in `day_of_week`, which is the whole reason that column
     * stays populated for one-off rows.
     *
     * @param array $attributes the row about to be written
     * @param int|null $ignoreId the row being updated, excluded from the search
     *
     * @return string|null an error translation key, or null when there is no clash
     */
    public static function classSchedule(array $attributes, ?int $ignoreId = null): ?string {
        $base = fn (): Builder => ClassSchedule::query()
            ->where('state', STATE_ACTIVE)
            ->where('semester_id', $attributes['semester_id'])
            ->where('day_of_week', $attributes['day_of_week'])
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId));

        $checks = [
            'instructor_id' => 'instructor_time_conflict',
            'room_id' => 'room_time_conflict',
            'section_id' => 'section_time_conflict',
        ];

        foreach ($checks as $column => $key) {
            // Each EXCLUDE constraint was predicated on `<column> IS NOT NULL`:
            // a meeting with no room booked cannot clash over a room.
            if (empty($attributes[$column])) {
                continue;
            }

            $clashes = static::overlapping(
                $base()->where($column, $attributes[$column]),
                $attributes['start_time'],
                $attributes['end_time'],
            );

            if ($clashes) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The clash an exam sitting would cause, or null when the slot is free.
     *
     * PostgreSQL overlapped these on a `tsrange` built from the date and the
     * times. Since `end_time > start_time` is a CHECK constraint, no sitting
     * spans midnight, so "same `exam_date` and overlapping clock times" is the
     * identical test.
     *
     * @param array $attributes the row about to be written
     * @param int|null $ignoreId the row being updated, excluded from the search
     *
     * @return string|null an error translation key, or null when there is no clash
     */
    public static function examSchedule(array $attributes, ?int $ignoreId = null): ?string {
        $examDate = static::asDate($attributes['exam_date']);

        $base = fn (): Builder => ExamSchedule::query()
            ->where('state', STATE_ACTIVE)
            ->whereDate('exam_date', $examDate)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId));

        $checks = [
            'room_id' => 'exam_room_time_conflict',
            'section_id' => 'exam_section_time_conflict',
        ];

        foreach ($checks as $column => $key) {
            if (empty($attributes[$column])) {
                continue;
            }

            $clashes = static::overlapping(
                $base()->where($column, $attributes[$column]),
                $attributes['start_time'],
                $attributes['end_time'],
            );

            if ($clashes) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether an invigilator is already on duty at an overlapping sitting.
     *
     * The `eia_no_double_booking` EXCLUDE constraint, restated. The companion
     * rule — the same person twice at the SAME exam — is still a real composite
     * unique index, so it stays the database's job and is not repeated here.
     *
     * @param array $attributes the row about to be written
     * @param int|null $ignoreId the row being updated, excluded from the search
     *
     * @return string|null an error translation key, or null when the duty is free
     */
    public static function invigilatorAssignment(array $attributes, ?int $ignoreId = null): ?string {
        $query = ExamInvigilatorAssignment::query()
            ->where('state', STATE_ACTIVE)
            ->where('instructor_id', $attributes['instructor_id'])
            ->whereDate('exam_date', static::asDate($attributes['exam_date']))
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId));

        return static::overlapping($query, $attributes['start_time'], $attributes['end_time'])
            ? 'invigilator_already_assigned'
            : null;
    }

    /**
     * Whether any row the query selects overlaps the given window.
     *
     * Half-open comparison, matching the `[)` bounds PostgreSQL's range
     * constructors default to: touching endpoints are not an overlap.
     *
     * The read is taken FOR UPDATE so the caller's transaction holds the range
     * it just found empty — without that lock the check and the write it guards
     * would be two independent statements with a window between them.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startTime
     * @param string $endTime
     *
     * @return bool
     */
    private static function overlapping(Builder $query, $startTime, $endTime): bool {
        return $query
            ->where('start_time', '<', static::asTime($endTime))
            ->where('end_time', '>', static::asTime($startTime))
            ->lockForUpdate()
            ->exists();
    }

    /**
     * A date as `Y-m-d`, whatever the caller had.
     *
     * Callers reach the guard from two directions: raw attribute arrays, where
     * `exam_date` is the string the database returned, and hydrated models,
     * where the `date` cast has already turned it into a Carbon. Normalising
     * here keeps every call site free of that distinction.
     *
     * @param mixed $value
     * @return string
     */
    private static function asDate($value): string {
        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    /**
     * A time of day as `H:i:s`, whatever the caller had.
     *
     * @param mixed $value
     * @return string
     */
    private static function asTime($value): string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = (string) $value;

        // A payload may carry `09:00`; the column holds `09:00:00`, and MySQL
        // compares TIME values, not strings — but pad anyway so the binding
        // never depends on that coercion.
        return strlen($value) === 5 ? $value . ':00' : $value;
    }
}
