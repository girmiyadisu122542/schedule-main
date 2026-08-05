<?php

namespace App\Models\Invigilation;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\People\Instructor;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One window in which the department declares an instructor available to
 * invigilate.
 *
 * A row means *available*; the absence of a row means *not offered*. There is
 * no status here and no `state` — a window either exists or it does not, and
 * the `ia_no_overlap` EXCLUDE constraint therefore applies to every row rather
 * than to a live subset.
 */
class InvigilatorAvailability extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'available_date' => 'date',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'instructor_id',
        'semester_id',
        'available_date',
        'start_time',
        'end_time',
        'submitted_by_id',
        'remark',
    ];

    /**
     * Relationship Instructor — the person being offered.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instructor(): BelongsTo {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Relationship Semester
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Relationship User — the department person who submitted the window. A
     * semantic relation, so it keeps its explicit FK argument; it is NOT a
     * `user()` creator relation, and this table has no `user_id`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function submitter(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * A readable label: "Dr. Alemu Bekele · 2026-06-22 09:00–12:00".
     *
     * @return string
     */
    public function displayLabel(): string {
        $instructor = $this->instructor?->full_name__localized;
        $when = trim(($this->available_date?->format(DATE_FORMAT) ?? '') . ' ' . $this->timeRange());

        return trim($instructor ? $instructor . ' · ' . $when : $when);
    }

    /**
     * The window as "09:00–12:00".
     *
     * @return string
     */
    public function timeRange(): string {
        return $this->shortTime($this->start_time) . '–' . $this->shortTime($this->end_time);
    }

    /**
     * Trim a `time` value down to "HH:MM".
     *
     * @param string|null $time
     * @return string
     */
    public function shortTime(?string $time): string {
        return $time ? date(ScheduleConstant::TIME_FORMAT, strtotime($time)) : '';
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::name(fn ($data) => $data->displayLabel()),
            Field::instructorId()->asInt(),
            Field::semesterId()->asInt(),
            Field::availableDate(fn ($data) => $data->available_date?->format(DATE_FORMAT)),
            Field::startTime(fn ($data) => $data->shortTime($data->start_time)),
            Field::endTime(fn ($data) => $data->shortTime($data->end_time)),
            Field::timeRange(fn ($data) => $data->timeRange()),
            Field::remark(),
            Field::makeResource('instructor', fields: 'idAndNameFields'),
            Field::makeResource('semester', fields: 'idAndNameFields'),
            Field::makeResource('submitted_by', 'submitter', fields: 'idAndNameFields'),
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
            Field::name(fn ($data) => $data->displayLabel()),
        ];
    }
}
