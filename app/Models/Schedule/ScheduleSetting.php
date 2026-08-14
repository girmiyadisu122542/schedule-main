<?php

namespace App\Models\Schedule;

use App\Models\Common\Lookup\LookupValue;
use App\Constants\ScheduleConstant;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The generation grid for one study mode.
 *
 * Replaces the hardcoded `App\Constants\ScheduleConstant::TEACHING_DAYS` and
 * `GENERATION_TIME_SLOTS`: which days a mode is taught on, when the day starts
 * and ends, how long a period runs, and when lunch is. A registrar edits this
 * under Configuration; the generator reads it per offering.
 */
class ScheduleSetting extends ScopedModel {

    /** Minutes in an hour — the unit everything here is measured in. */
    private const MINUTES_PER_HOUR = 60;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'teaching_days' => 'array',
        'exam_days' => 'array',
        'exam_type_durations' => 'array',
        'max_exams_per_day' => 'integer',
        'min_hours_between_exams' => 'integer',
        'students_per_invigilator' => 'integer',
        'min_invigilators_per_room' => 'integer',
        'weight_spread_sessions' => 'integer',
        'weight_avoid_gaps' => 'integer',
        'weight_room_fit' => 'integer',
        'weight_same_building' => 'integer',
        'allow_cross_campus_day' => 'boolean',
        'period_minutes' => 'integer',
        'break_minutes' => 'integer',
        'exam_duration_minutes' => 'integer',
        'exam_gap_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'study_mode_lookup_value_id',
        'teaching_days',
        'day_start',
        'day_end',
        'period_minutes',
        'break_minutes',
        'lunch_start',
        'lunch_end',
        'exam_days',
        'exam_day_start',
        'exam_day_end',
        'exam_duration_minutes',
        'exam_type_durations',
        'max_exams_per_day',
        'min_hours_between_exams',
        'students_per_invigilator',
        'min_invigilators_per_room',
        'weight_spread_sessions',
        'weight_avoid_gaps',
        'weight_room_fit',
        'weight_same_building',
        'allow_cross_campus_day',
        'exam_gap_minutes',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship LookupValue — the STUDY_MODE this grid belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function studyMode(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'study_mode_lookup_value_id');
    }

    /**
     * Relationship User — the record creator.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * The periods this grid produces.
     *
     * Walks from `day_start` in `period_minutes` steps separated by
     * `break_minutes`, stopping at `day_end`, and skips any period that would
     * run into lunch. Deriving them is what guarantees the lunch break is
     * actually free — when the periods were a hand-written list, nothing
     * checked that the gap in the middle was still there.
     *
     * A period is only emitted if it fits ENTIRELY before `day_end`: a
     * half-length period at the end of the day is not a period anyone teaches.
     *
     * @return array<int, array<string, string>> `[['start' => 'H:i', 'end' => 'H:i'], ...]`
     */
    public function periods(): array {
        $cursor = $this->minutesOf($this->day_start);
        $dayEnd = $this->minutesOf($this->day_end);
        $lunchStart = $this->lunch_start ? $this->minutesOf($this->lunch_start) : null;
        $lunchEnd = $this->lunch_end ? $this->minutesOf($this->lunch_end) : null;

        $length = max(1, (int) $this->period_minutes);
        $gap = max(0, (int) $this->break_minutes);

        $periods = [];

        // The step is at least a minute even with a zero-length period, so a
        // mis-entered setting cannot spin here forever.
        while ($cursor + $length <= $dayEnd) {
            $end = $cursor + $length;

            // Overlapping lunch at all: jump the whole break rather than
            // shortening the period around it.
            if ($lunchStart !== null && $lunchEnd !== null && $cursor < $lunchEnd && $end > $lunchStart) {
                $cursor = $lunchEnd;

                continue;
            }

            $periods[] = ['start' => $this->clock($cursor), 'end' => $this->clock($end)];
            $cursor = $end + $gap;
        }

        return $periods;
    }

    /**
     * The sitting windows this grid offers for an exam of a given length.
     *
     * Unlike teaching periods, the length is NOT a property of the grid — an
     * exam runs as long as its course says it does, and a two-hour paper and a
     * three-hour paper cannot share a boundary. So the windows are computed per
     * duration: walk the exam day in `duration + exam_gap_minutes` steps, and
     * emit only those that fit whole before the day ends.
     *
     * @param int $durationMinutes how long this particular exam runs
     * @return array<int, array<string, string>> `[['start' => 'H:i', 'end' => 'H:i'], ...]`
     */
    public function examWindows(int $durationMinutes): array {
        $cursor = $this->minutesOf($this->exam_day_start);
        $dayEnd = $this->minutesOf($this->exam_day_end);

        $length = max(1, $durationMinutes);
        $gap = max(0, (int) $this->exam_gap_minutes);

        $windows = [];

        while ($cursor + $length <= $dayEnd) {
            $end = $cursor + $length;
            $windows[] = ['start' => $this->clock($cursor), 'end' => $this->clock($end)];
            $cursor = $end + $gap;
        }

        return $windows;
    }

    /**
     * Minutes past midnight for a stored time.
     *
     * @param mixed $time
     * @return int
     */
    private function minutesOf($time): int {
        $parsed = Carbon::parse((string) $time);

        return $parsed->hour * self::MINUTES_PER_HOUR + $parsed->minute;
    }

    /**
     * Minutes past midnight back to `H:i`.
     *
     * @param int $minutes
     * @return string
     */
    private function clock(int $minutes): string {
        return sprintf('%02d:%02d', intdiv($minutes, self::MINUTES_PER_HOUR), $minutes % self::MINUTES_PER_HOUR);
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::studyModeLookupValueId()->asInt(),
            Field::teachingDays(),
            Field::dayStart(fn ($data) => $data->shortTime($data->day_start)),
            Field::dayEnd(fn ($data) => $data->shortTime($data->day_end)),
            Field::periodMinutes()->asInt(),
            Field::breakMinutes()->asInt(),
            Field::lunchStart(fn ($data) => $data->lunch_start ? $data->shortTime($data->lunch_start) : null),
            Field::lunchEnd(fn ($data) => $data->lunch_end ? $data->shortTime($data->lunch_end) : null),
            Field::examDays(),
            Field::examDayStart(fn ($data) => $data->shortTime($data->exam_day_start)),
            Field::examDayEnd(fn ($data) => $data->shortTime($data->exam_day_end)),
            Field::examDurationMinutes()->asInt(),
            Field::examGapMinutes()->asInt(),
            Field::examTypeDurations(),
            Field::maxExamsPerDay()->asInt(),
            Field::minHoursBetweenExams()->asInt(),
            Field::studentsPerInvigilator()->asInt(),
            Field::minInvigilatorsPerRoom()->asInt(),
            Field::weightSpreadSessions()->asInt(),
            Field::weightAvoidGaps()->asInt(),
            Field::weightRoomFit()->asInt(),
            Field::weightSameBuilding()->asInt(),
            Field::allowCrossCampusDay()->asBool(),
            Field::isActive()->asBool(),
            // The grid this setting actually produces — what the generator will
            // use and what the Configuration screen previews.
            Field::make('periods', fn ($data) => $data->periods()),
            // The sittings a DEFAULT-length exam gets. A course with its own
            // length gets its own windows at generation time.
            Field::make('exam_windows', fn ($data) => $data->examWindows((int) $data->exam_duration_minutes)),
            Field::studyModeCode('studyMode.code'),
            Field::makeResource('study_mode', 'studyMode', fields: 'idAndNameFields'),
            Field::makeResource('created_by', 'user', fields: 'idAndNameFields'),
            Field::createdAt(fn ($data) => $data->created_at->format(DATE_FORMAT)),
        ];
    }

    /**
     * Compact shape used by dropdowns and embedded resources.
     *
     * @return array
     */
    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::name(fn ($data) => $data->studyMode?->name__localized ?? ''),
        ];
    }

    /**
     * `H:i` for a stored time, matching how the schedule models print theirs.
     *
     * @param mixed $time
     * @return string
     */
    public function shortTime($time): string {
        return $time ? date(ScheduleConstant::TIME_FORMAT, strtotime((string) $time)) : '';
    }
}
