<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Schedule\ScheduleSetting;
use App\Models\Schedule\SemesterExamPeriod;

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
     * Declared exam windows resolved this run, keyed "semesterId:examTypeCode".
     *
     * @var array<string, array{start: string, end: string}|null>
     */
    private array $windowCache = [];

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
    public function examDurationFor(CourseOffering $offering, ?ScheduleSetting $setting, ?string $examTypeCode = null): int {
        $courseDuration = $offering->course?->exam_duration_minutes;
        if ($courseDuration) {
            return (int) $courseDuration;
        }

        // Then the exam TYPE. A midterm is rarely as long as a final, and a
        // course that declares nothing should still get the right length for
        // the paper being sat rather than one number for every type.
        $typeDuration = $this->examTypeDuration($setting, $examTypeCode);
        if ($typeDuration) {
            return $typeDuration;
        }

        return (int) ($setting?->exam_duration_minutes ?: self::fallbackExamDuration());
    }

    /**
     * The duration this setting declares for one exam type, if any.
     *
     * Stored as a code-keyed map so adding an exam type to the lookup
     * catalogue does not need a migration — the same reason every enum in this
     * system resolves by code rather than by id.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @param string|null $examTypeCode
     *
     * @return int|null minutes, null when the type is not configured
     */
    public function examTypeDuration(?ScheduleSetting $setting, ?string $examTypeCode): ?int {
        if (!$examTypeCode) {
            return null;
        }

        $minutes = ($setting?->exam_type_durations ?? [])[$examTypeCode] ?? null;

        return $minutes ? (int) $minutes : null;
    }

    /**
     * The dates an exam period covers, as declared by the registrar.
     *
     * Returns null when the semester has no window for this type, which is the
     * signal to fall back to the semester's own exam period, which is
     * mandatory and therefore always set.
     *
     * @param \App\Models\Academic\Semester $semester
     * @param string|null $examTypeCode
     *
     * @return array{start: string, end: string}|null
     */
    public function declaredExamWindow(Semester $semester, ?string $examTypeCode): ?array {
        if (!$examTypeCode) {
            return null;
        }

        $cacheKey = $semester->id . ':' . $examTypeCode;
        if (array_key_exists($cacheKey, $this->windowCache)) {
            return $this->windowCache[$cacheKey];
        }

        $period = SemesterExamPeriod::query()
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->whereHas('examType', fn ($query) => $query->where('code', $examTypeCode))
            ->first();

        return $this->windowCache[$cacheKey] = $period
            ? ['start' => $period->start_date->toDateString(), 'end' => $period->end_date->toDateString()]
            : null;
    }

    /**
     * The most exams one cohort may sit in a single day.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return int
     */
    public function maxExamsPerDay(?ScheduleSetting $setting): int {
        return max(1, (int) ($setting?->max_exams_per_day ?: ScheduleConstant::DEFAULT_MAX_EXAMS_PER_DAY));
    }

    /**
     * The rest a cohort is owed between two of its exams, in minutes.
     *
     * Zero means "only the overlap rule applies", which is what the database
     * enforced on its own before this setting existed.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return int
     */
    public function minMinutesBetweenExams(?ScheduleSetting $setting): int {
        return max(0, (int) ($setting?->min_hours_between_exams ?? 0)) * ScheduleConstant::MINUTES_PER_HOUR;
    }

    /**
     * How many invigilators a hall of this occupancy needs.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @param int $seated how many sit in this hall
     *
     * @return int
     */
    public function invigilatorsFor(?ScheduleSetting $setting, int $seated): int {
        $perInvigilator = max(1, (int) ($setting?->students_per_invigilator ?: ScheduleConstant::DEFAULT_STUDENTS_PER_INVIGILATOR));
        $minimum = max(1, (int) ($setting?->min_invigilators_per_room ?: 1));

        return max($minimum, (int) ceil(max(0, $seated) / $perInvigilator));
    }

    /**
     * The soft-constraint weights, with every key guaranteed present.
     *
     * A weight of zero switches its preference off — the documented way to opt
     * out of a preference without touching code.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return array<string, int>
     */
    public function weights(?ScheduleSetting $setting): array {
        return [
            'spread_sessions' => (int) ($setting?->weight_spread_sessions ?? ScheduleConstant::DEFAULT_WEIGHT_SPREAD),
            'avoid_gaps' => (int) ($setting?->weight_avoid_gaps ?? ScheduleConstant::DEFAULT_WEIGHT_GAPS),
            'room_fit' => (int) ($setting?->weight_room_fit ?? ScheduleConstant::DEFAULT_WEIGHT_ROOM_FIT),
            'same_building' => (int) ($setting?->weight_same_building ?? ScheduleConstant::DEFAULT_WEIGHT_BUILDING),
        ];
    }

    /**
     * Whether a cohort may be sent between campuses within one day.
     *
     * A building change is a preference the scoring penalises; a campus change
     * is a rejection, because the cohort cannot physically arrive.
     *
     * @param \App\Models\Schedule\ScheduleSetting|null $setting
     * @return bool
     */
    public function allowsCrossCampusDay(?ScheduleSetting $setting): bool {
        return (bool) ($setting?->allow_cross_campus_day ?? false);
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
