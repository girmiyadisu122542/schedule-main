<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Offering\CourseOffering;
use App\Models\Schedule\ScheduleSetting;

/**
 * Which generation grid an offering is scheduled into.
 *
 * A regular programme is taught Monday–Friday in the day; an extension
 * programme at the weekend. The grid is a property of the STUDY MODE, and an
 * offering reaches its mode through its programme — so this resolves
 * offering → programme → study mode → grid.
 *
 * Nothing here throws when a grid is missing. The hardcoded constants remain as
 * the last resort so a database that has not been seeded still generates a
 * sensible weekday timetable rather than nothing at all.
 */
class ScheduleSettingService {

    /**
     * Settings already resolved this run, keyed by study-mode value id.
     *
     * A generation run asks once per offering and a semester has hundreds.
     *
     * @var array<string, \App\Models\Schedule\ScheduleSetting|null>
     */
    private array $cache = [];

    /**
     * The grid for one offering.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return \App\Models\Schedule\ScheduleSetting|null null when nothing is configured
     */
    public function forOffering(CourseOffering $offering): ?ScheduleSetting {
        // The offering's own programme, or the one its cohort belongs to —
        // `program_id` is nullable and the section carries the authoritative
        // programme (Final Schema.md §8, §12).
        $modeId = $offering->program?->study_mode_lookup_value_id
            ?? $offering->section?->program?->study_mode_lookup_value_id;

        return $this->forStudyMode($modeId ? (int) $modeId : null);
    }

    /**
     * The grid for one study mode, falling back to the default mode's grid.
     *
     * @param int|null $studyModeValueId
     * @return \App\Models\Schedule\ScheduleSetting|null
     */
    public function forStudyMode(?int $studyModeValueId): ?ScheduleSetting {
        $key = (string) ($studyModeValueId ?? 'default');

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $setting = $studyModeValueId
            ? ScheduleSetting::query()
                ->with('studyMode')
                ->where('study_mode_lookup_value_id', $studyModeValueId)
                ->where('is_active', true)
                ->first()
            : null;

        // A programme with no mode set — or a mode nobody has configured a grid
        // for — is taught on the regular timetable.
        $setting ??= $this->regularSetting();

        return $this->cache[$key] = $setting;
    }

    /**
     * The grid configured for the regular study mode, if there is one.
     *
     * @return \App\Models\Schedule\ScheduleSetting|null
     */
    private function regularSetting(): ?ScheduleSetting {
        if (array_key_exists('regular', $this->cache)) {
            return $this->cache['regular'];
        }

        return $this->cache['regular'] = ScheduleSetting::query()
            ->with('studyMode')
            ->where('is_active', true)
            ->whereHas('studyMode', fn ($query) => $query->where('code', STUDY_MODE_REGULAR))
            ->first();
    }

    /**
     * The teaching days a setting declares, or the hardcoded weekdays.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return array<int, int> ISO-8601 day numbers
     */
    public function teachingDays(?ScheduleSetting $setting): array {
        $days = array_values(array_filter(array_map('intval', $setting->teaching_days ?? [])));

        return $days ?: ScheduleConstant::TEACHING_DAYS;
    }

    /**
     * The periods a setting produces, or the hardcoded grid.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return array<int, array<string, string>>
     */
    public function periods(?ScheduleSetting $setting): array {
        $periods = $setting?->periods() ?? [];

        return $periods ?: ScheduleConstant::GENERATION_TIME_SLOTS;
    }

    /**
     * The days exams may be held on, or the hardcoded weekdays.
     *
     * Separate from `teachingDays()` on purpose: an institution that teaches
     * Monday–Friday commonly examines on Saturday too, and the weekend intake
     * examines when it is actually on campus.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return array<int, int> ISO-8601 day numbers
     */
    public function examDays(?ScheduleSetting $setting): array {
        $days = array_values(array_filter(array_map('intval', $setting->exam_days ?? [])));

        return $days ?: ScheduleConstant::TEACHING_DAYS;
    }

    /**
     * How long one offering's exam runs.
     *
     * The COURSE decides — a three-hour final and a ninety-minute quiz are not
     * the same sitting — and the study mode's setting is only the default for
     * the courses that do not say.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     *
     * @return int minutes
     */
    public function examDurationFor(CourseOffering $offering, ?ScheduleSetting $setting): int {
        $courseDuration = $offering->course?->exam_duration_minutes;
        if ($courseDuration) {
            return (int) $courseDuration;
        }

        return (int) ($setting?->exam_duration_minutes ?: self::fallbackExamDuration());
    }

    /**
     * The sitting windows available for an exam of this length.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @param int $durationMinutes
     *
     * @return array<int, array<string, string>>
     */
    public function examWindows(?ScheduleSetting $setting, int $durationMinutes): array {
        $windows = $setting?->examWindows($durationMinutes) ?? [];

        return $windows ?: ScheduleConstant::EXAM_TIME_SLOTS;
    }

    /**
     * How many days before the semester ends the exam period opens.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return int
     */
    public function examPeriodDays(?ScheduleSetting $setting): int {
        return (int) ($setting?->exam_period_days ?: ScheduleConstant::EXAM_PERIOD_DAYS);
    }

    /**
     * The length of the hardcoded exam slot, used when nothing is configured.
     *
     * @return int minutes
     */
    private static function fallbackExamDuration(): int {
        $slot = ScheduleConstant::EXAM_TIME_SLOTS[0] ?? null;
        if (!$slot) {
            return 180;
        }

        return (int) round((strtotime($slot['end']) - strtotime($slot['start'])) / 60);
    }
}
