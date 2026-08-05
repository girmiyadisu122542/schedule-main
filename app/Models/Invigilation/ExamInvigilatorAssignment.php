<?php

namespace App\Models\Invigilation;

use App\Constants\ScheduleConstant;
use App\Models\Common\Lookup\LookupValue;
use App\Models\People\Instructor;
use App\Models\Schedule\ExamSchedule;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One instructor on duty at one exam.
 *
 * `exam_date`, `start_time` and `end_time` are copies of the sitting's own,
 * kept in step by a composite foreign key with `ON UPDATE CASCADE` — moving an
 * exam moves every duty with it and re-checks `eia_no_double_booking` against
 * the new time automatically.
 *
 * `state` is the conflict-liveness flag: declining or being replaced sets it to
 * STATE_INACTIVE, which frees the invigilator without erasing the record.
 */
class ExamInvigilatorAssignment extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'exam_date' => 'date',
        'assigned_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'exam_schedule_id',
        'instructor_id',
        'exam_date',
        'start_time',
        'end_time',
        'role_lookup_value_id',
        'status_lookup_value_id',
        'state',
        'assigned_by_id',
        'assigned_at',
        'remark',
    ];

    /**
     * Relationship ExamSchedule — the sitting being invigilated.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function examSchedule(): BelongsTo {
        return $this->belongsTo(ExamSchedule::class);
    }

    /**
     * Relationship Instructor — who is on duty.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instructor(): BelongsTo {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Relationship LookupValue — chief or assistant. A semantic relation, so it
     * keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'role_lookup_value_id');
    }

    /**
     * Relationship LookupValue — assigned / accepted / declined / replaced.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    /**
     * Relationship User — who put this person on duty.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assignedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /**
     * A readable label: "Dr. Alemu Bekele · CS101 … Final (2026-06-22 09:00–12:00)".
     *
     * @return string
     */
    public function displayLabel(): string {
        $instructor = $this->instructor?->full_name__localized;
        $exam = $this->examSchedule?->displayLabel();

        return trim(implode(' · ', array_filter([$instructor, $exam])));
    }

    /**
     * The duty window as "09:00–12:00".
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
            Field::examScheduleId()->asInt(),
            Field::instructorId()->asInt(),
            Field::roleLookupValueId()->asInt(),
            Field::statusLookupValueId()->asInt(),
            Field::state()->asInt(),
            Field::examDate(fn ($data) => $data->exam_date?->format(DATE_FORMAT)),
            Field::startTime(fn ($data) => $data->shortTime($data->start_time)),
            Field::endTime(fn ($data) => $data->shortTime($data->end_time)),
            Field::timeRange(fn ($data) => $data->timeRange()),
            Field::remark(),
            // The chips read the stable codes + the lookup values' own colours.
            Field::statusCode('status.code'),
            Field::roleCode('role.code'),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('role', fields: 'idAndNameFields'),
            Field::makeResource('exam_schedule', 'examSchedule', fields: 'idAndNameFields'),
            Field::makeResource('instructor', fields: 'idAndNameFields'),
            Field::makeResource('assigned_by', 'assignedBy', fields: 'idAndNameFields'),
            Field::assignedAt(fn ($data) => $data->assigned_at?->format(DATETIME_FORMAT)),
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
