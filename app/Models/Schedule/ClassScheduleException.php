<?php

namespace App\Models\Schedule;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One suppressed occurrence of a recurring class.
 *
 * "Not this Monday — it is a public holiday." The weekly rule on
 * `class_schedules` stays live, so the room and the instructor stay booked for
 * every other week; only this date is skipped when the timetable is rendered.
 */
class ClassScheduleException extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'exception_date' => 'date',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'class_schedule_id',
        'exception_date',
        'reason',
        'created_by_id',
    ];

    /**
     * Relationship ClassSchedule — the weekly rule this date is cut out of.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classSchedule(): BelongsTo {
        return $this->belongsTo(ClassSchedule::class);
    }

    /**
     * Relationship User — who cancelled the week.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_id');
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
            Field::classScheduleId()->asInt(),
            Field::exceptionDate(fn ($data) => $data->exception_date?->format(DATE_FORMAT)),
            Field::reason(),
            Field::makeResource('created_by', 'createdBy', fields: 'idAndNameFields'),
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
            Field::exceptionDate(fn ($data) => $data->exception_date?->format(DATE_FORMAT)),
            Field::reason(),
        ];
    }
}
