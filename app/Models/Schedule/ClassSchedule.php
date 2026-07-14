<?php

namespace App\Models\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Type\ScheduleType\ScheduleType;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSchedule extends ScopedModel {
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'exam_date' => 'date',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'code',
        'schedule_type',
        'day_of_week',
        'exam_date',
        'start_time',
        'end_time',
        'room',
        'section',
        'state',
        'user_id',
    ];

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::code(),
            Field::state(),
            Field::room(),
            Field::section(),
            Field::scheduleType(),
            Field::dayOfWeek(),
            Field::startTime('start_time'),
            Field::endTime('end_time'),
            Field::name('name__localized'),
            Field::examDate(fn ($data) => $data->exam_date?->format(DATE_FORMAT)),
            Field::makeType('schedule_type_data', 'schedule_type', typeClass: ScheduleType::class, typeFunction: 'getFullTypeUsingId'),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
            Field::makeResource('created_by', 'user', fields: 'idAndNameFields'),
            Field::createdAt(fn ($data) => $data->created_at->format(DATE_FORMAT)),
        ];
    }

    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::code(),
            Field::name('name__localized'),
        ];
    }

    /**
     * Whether this row is an exam schedule.
     *
     * @return bool
     */
    public function isExam(): bool {
        return (int) $this->schedule_type === ScheduleConstant::TYPE_EXAM;
    }
}
