<?php

namespace App\Services\Schedule;

use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use Illuminate\Support\Collection;

/**
 * Ranks candidate placements so the generator stops taking the first free slot.
 *
 * The engine's correctness comes from the EXCLUDE constraints: whatever this
 * class prefers, an illegal placement is still rejected by the database. What
 * scoring adds is the difference between a timetable that is merely legal and
 * one a cohort can actually live with — sessions spread across the week rather
 * than stacked on Monday, no two-hour hole in the middle of a day, a room that
 * fits rather than a lecture hall for twelve people.
 *
 * Weights come from `schedule_settings`, so a registrar retunes the preferences
 * without a deploy, and a weight of zero switches one off entirely.
 */
class PlacementScorer {

    /** What one cohort is already committed to this semester, by day. */
    private array $sectionDayCache = [];

    /**
     * Score one candidate placement. Higher is better.
     *
     * @param array<string, int> $weights from ScheduleSettingService::weights()
     * @param int $day ISO-8601 day number
     * @param array<string, string> $slot {start, end}
     * @param \App\Models\Physical\Room $room
     * @param int $expectedStudents
     * @param array<int, int> $usedDays days this offering already meets on
     * @param \Illuminate\Support\Collection $sectionDay the cohort's other sessions that day
     *
     * @return int
     */
    public function score(array $weights, int $day, array $slot, ?Room $room, int $expectedStudents, array $usedDays, Collection $sectionDay): int {
        $score = 0;

        // Spread: a course meeting twice wants Monday and Thursday, not Monday
        // twice. This is the preference that most changes how a week reads.
        if (!in_array($day, $usedDays, true)) {
            $score += $weights['spread_sessions'];
        }

        // Room fit: prefer the smallest room that still holds the cohort.
        // Scaled by how much of the room is used, so a 40-seat class in a
        // 45-seat room beats the same class in a 300-seat auditorium.
        //
        // `$room` is null when the offering's department owns no rooms: the
        // meeting is placed on the timetable with its room left blank, so
        // there is no room to fit and nothing to compare buildings against.
        // The day and gap preferences still apply, which is what keeps a
        // roomless week as well spread as any other.
        if ($room) {
            $capacity = max(1, (int) $room->capacity);
            $utilisation = min(1.0, max(0, $expectedStudents) / $capacity);
            $score += (int) round($weights['room_fit'] * $utilisation);
        }

        if ($sectionDay->isNotEmpty()) {
            // Gaps: a session butting onto one the cohort already has is worth
            // more than one stranded two periods away with idle time between.
            $score += $this->adjacencyBonus($weights['avoid_gaps'], $slot, $sectionDay);

            // Building: moving a cohort across campus between periods is a
            // rejection elsewhere; moving it between buildings is merely
            // unwelcome, so it is priced rather than forbidden.
            if ($room && $this->sharesBuilding($room, $sectionDay)) {
                $score += $weights['same_building'];
            }
        }

        return $score;
    }

    /**
     * Reward a slot that sits next to what the cohort already has that day.
     *
     * Full weight when the session touches an existing one, tapering to nothing
     * as the idle gap grows past two hours.
     *
     * @param int $weight
     * @param array<string, string> $slot
     * @param \Illuminate\Support\Collection $sectionDay
     *
     * @return int
     */
    private function adjacencyBonus(int $weight, array $slot, Collection $sectionDay): int {
        if ($weight <= 0) {
            return 0;
        }

        $start = strtotime($slot['start']);
        $end = strtotime($slot['end']);

        $smallestGap = null;
        foreach ($sectionDay as $existing) {
            $existingStart = strtotime($existing->start_time);
            $existingEnd = strtotime($existing->end_time);

            // Distance between the two windows; zero when they touch.
            $gap = $start >= $existingEnd
                ? $start - $existingEnd
                : ($existingStart >= $end ? $existingStart - $end : 0);

            $smallestGap = $smallestGap === null ? $gap : min($smallestGap, $gap);
        }

        if ($smallestGap === null) {
            return 0;
        }

        $gapHours = $smallestGap / 3600;
        if ($gapHours >= 2) {
            return 0;
        }

        return (int) round($weight * (1 - ($gapHours / 2)));
    }

    /**
     * Whether this room is in a building the cohort is already in that day.
     *
     * @param \App\Models\Physical\Room $room
     * @param \Illuminate\Support\Collection $sectionDay
     *
     * @return bool
     */
    private function sharesBuilding(Room $room, Collection $sectionDay): bool {
        return $sectionDay->contains(fn ($existing) => $existing->room?->building_id === $room->building_id);
    }

    /**
     * Whether this room would send the cohort to another campus that day.
     *
     * Unlike a building change this is not a preference: a cohort with a class
     * on one campus cannot be in a room on another twenty minutes later.
     *
     * @param \App\Models\Physical\Room $room
     * @param \Illuminate\Support\Collection $sectionDay
     *
     * @return bool
     */
    public function crossesCampus(Room $room, Collection $sectionDay): bool {
        $campusId = $room->building?->campus_id;
        if (!$campusId) {
            return false;
        }

        return $sectionDay->contains(function ($existing) use ($campusId) {
            $otherCampus = $existing->room?->building?->campus_id;

            return $otherCampus !== null && $otherCampus !== $campusId;
        });
    }

    /**
     * What a cohort already has on one day of the week, this semester.
     *
     * Cached for the run: a semester has hundreds of offerings and they share
     * a few dozen cohorts between them.
     *
     * @param int|null $sectionId
     * @param int $semesterId
     * @param int $day
     *
     * @return \Illuminate\Support\Collection
     */
    public function sectionDay(?int $sectionId, int $semesterId, int $day): Collection {
        if (!$sectionId) {
            return collect();
        }

        $key = "{$sectionId}:{$semesterId}:{$day}";
        if (array_key_exists($key, $this->sectionDayCache)) {
            return $this->sectionDayCache[$key];
        }

        return $this->sectionDayCache[$key] = ClassSchedule::query()
            ->with('room.building')
            ->where('section_id', $sectionId)
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day)
            ->where('state', STATE_ACTIVE)
            ->get();
    }

    /**
     * Forget everything cached — call after each write, since the write is
     * exactly what makes the cached picture of a cohort's day stale.
     *
     * @param int|null $sectionId
     * @param int $semesterId
     * @param int $day
     *
     * @return void
     */
    public function forget(?int $sectionId, int $semesterId, int $day): void {
        unset($this->sectionDayCache["{$sectionId}:{$semesterId}:{$day}"]);
    }
}
