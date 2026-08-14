<?php

namespace App\Services\Schedule;

use App\Models\People\Instructor;
use App\Models\Schedule\ClassSchedule;

/**
 * How much an instructor is already teaching, against what they may.
 *
 * `instructors.max_weekly_hours` was collected, validated and stored, and read
 * by nothing — so it looked like a guarantee while being purely decorative and
 * instructors were silently overloaded. This is the enforcement.
 *
 * A null limit means unlimited, which is the honest reading of "the registrar
 * has not set one" — it must not be treated as zero.
 */
class InstructorWorkloadService {

    /** Minutes per hour, since schedules are stored as times and limits as hours. */
    private const MINUTES_PER_HOUR = 60;

    /** Committed weekly minutes this run, keyed "instructorId:semesterId". */
    private array $cache = [];

    /**
     * Whether this instructor can take on another session of this length.
     *
     * @param int|null $instructorId
     * @param int $semesterId
     * @param int $additionalMinutes
     *
     * @return bool true when there is no limit, or the session fits under it
     */
    public function canTake(?int $instructorId, int $semesterId, int $additionalMinutes): bool {
        if (!$instructorId) {
            return true;
        }

        $limit = $this->limitMinutes($instructorId);
        if ($limit === null) {
            return true;
        }

        return ($this->committedMinutes($instructorId, $semesterId) + $additionalMinutes) <= $limit;
    }

    /**
     * The instructor's ceiling in minutes, or null when they have none.
     *
     * @param int $instructorId
     * @return int|null
     */
    public function limitMinutes(int $instructorId): ?int {
        $hours = Instructor::query()->whereKey($instructorId)->value('max_weekly_hours');

        return $hours === null ? null : (int) round((float) $hours * self::MINUTES_PER_HOUR);
    }

    /**
     * Minutes already committed in a normal teaching week.
     *
     * Counts live rows only: a cancelled session has released its slot and
     * should not count against the person who is no longer teaching it.
     *
     * @param int $instructorId
     * @param int $semesterId
     *
     * @return int
     */
    public function committedMinutes(int $instructorId, int $semesterId): int {
        $key = "{$instructorId}:{$semesterId}";
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $minutes = ClassSchedule::query()
            ->where('instructor_id', $instructorId)
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            // A one-off makeup class is not part of the weekly load.
            ->whereNull('specific_date')
            ->get(['start_time', 'end_time'])
            ->sum(fn ($row) => max(0, (strtotime($row->end_time) - strtotime($row->start_time)) / 60));

        return $this->cache[$key] = (int) round($minutes);
    }

    /**
     * Drop the cached total for one instructor — call after writing a session,
     * because that write is what makes the total wrong.
     *
     * @param int|null $instructorId
     * @param int $semesterId
     *
     * @return void
     */
    public function forget(?int $instructorId, int $semesterId): void {
        unset($this->cache["{$instructorId}:{$semesterId}"]);
    }
}
