<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Offering\CourseOffering;
use App\Models\Schedule\ScheduleSetting;

/**
 * How many weekly meetings one offering is entitled to, and of what kind.
 *
 * Extracted so there is ONE answer to "what is this course's week". It used to
 * live privately inside the generator, which meant the generator respected a
 * course's declared load and hand-placement ignored it completely: a course
 * declaring four meetings could be given a fifth, a tenth, by clicking Create
 * again — and nothing said so.
 *
 * The figures come from the course's declared hours divided by the length of a
 * teaching period, rounded UP, because teaching less than the catalogue says is
 * the worse error. `sessions_per_week` remains the fallback for a course that
 * declares no hours at all.
 */
class CourseWeeklyLoadService {

    /**
     * The meetings an offering needs, as SESSION_TYPE codes.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param \App\Models\Schedule\ScheduleSetting|null $setting the grid in force
     *
     * @return array<int, string>
     */
    public function meetingsFor(CourseOffering $offering, ?ScheduleSetting $setting = null): array {
        $course = $offering->course;
        $periodHours = $this->periodHours($setting);

        $counts = [
            SESSION_TYPE_LECTURE => $this->sessionsForHours($course?->lecture_hours_per_week, $periodHours),
            SESSION_TYPE_LAB => $this->sessionsForHours($course?->lab_hours_per_week, $periodHours),
            SESSION_TYPE_TUTORIAL => $this->sessionsForHours($course?->tutorial_hours_per_week, $periodHours),
        ];

        if (array_sum($counts) === 0) {
            $sessions = (int) ($course?->sessions_per_week ?: ScheduleConstant::DEFAULT_SESSIONS_PER_WEEK);
            $counts[SESSION_TYPE_LECTURE] = max(1, min($sessions, ScheduleConstant::MAX_SESSIONS_PER_WEEK));
        }

        return $this->capMeetings($counts);
    }

    /**
     * How many meetings this offering may have in total.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     *
     * @return int
     */
    public function meetingCountFor(CourseOffering $offering, ?ScheduleSetting $setting = null): int {
        return count($this->meetingsFor($offering, $setting));
    }

    /**
     * Flatten per-type counts into a meeting list, within the weekly cap.
     *
     * The cap is applied by allocation, not truncation: a course wanting nine
     * meetings on a five-meeting ceiling keeps at least one of every type it
     * declared, and the remaining budget goes largest-need-first. Cutting the
     * tail off a concatenated list would silently delete every tutorial and
     * most labs, turning a lab course into a lecture course.
     *
     * @param array<string, int> $counts
     * @return array<int, string>
     */
    private function capMeetings(array $counts): array {
        $counts = array_filter($counts, fn (int $count): bool => $count > 0);
        $cap = ScheduleConstant::MAX_SESSIONS_PER_WEEK;

        if (array_sum($counts) > $cap) {
            $allocated = array_map(fn (): int => 1, $counts);
            $remaining = $cap - count($allocated);

            while ($remaining > 0) {
                $shortfalls = [];
                foreach ($counts as $type => $wanted) {
                    $shortfalls[$type] = $wanted - $allocated[$type];
                }

                if (max($shortfalls) <= 0) {
                    break;
                }

                $type = array_search(max($shortfalls), $shortfalls, true);
                $allocated[$type]++;
                $remaining--;
            }

            $counts = $allocated;
        }

        $meetings = [];
        foreach ($counts as $type => $count) {
            $meetings = array_merge($meetings, array_fill(0, $count, $type));
        }

        return $meetings;
    }

    /**
     * How many periods a weekly hour figure needs, rounded up.
     *
     * @param float|string|null $hours
     * @param float $periodHours
     *
     * @return int
     */
    private function sessionsForHours($hours, float $periodHours): int {
        $hours = (float) ($hours ?? 0);

        if ($hours <= 0) {
            return 0;
        }

        return max(1, (int) ceil($hours / max(0.25, $periodHours)));
    }

    /**
     * The length of one teaching period, in hours.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return float
     */
    private function periodHours(?ScheduleSetting $setting): float {
        $minutes = (int) ($setting?->period_minutes ?: 0);

        if ($minutes <= 0) {
            $slot = ScheduleConstant::GENERATION_TIME_SLOTS[0] ?? null;
            $minutes = $slot
                ? (int) round((strtotime($slot['end']) - strtotime($slot['start'])) / 60)
                : 60;
        }

        return max(0.25, $minutes / 60);
    }
}
