<?php

namespace App\Models\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\Physical\Room;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Type\DayOfWeek\DayOfWeek;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recurring weekly class meeting — the output of class scheduling.
 *
 * `semester_id` and `section_id` are copies of the offering's own values, kept
 * in step by composite foreign keys. They exist so the three conflict EXCLUDE
 * constraints can read them off this row.
 *
 * There is no `is_active` here and no soft delete. `state` is the
 * constraint-liveness flag the EXCLUDE predicates read; cancelling a meeting
 * sets `status -> cancelled` AND `state -> STATE_INACTIVE` in one write, which
 * frees its room, instructor and section slot.
 */
class ClassSchedule extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'course_offering_id',
        'semester_id',
        'section_id',
        'instructor_id',
        'room_id',
        'session_type_lookup_value_id',
        'day_of_week',
        'start_time',
        'end_time',
        'status_lookup_value_id',
        'state',
        'generation_run_id',
        'created_by_id',
        'published_by_id',
        'published_at',
    ];

    /**
     * Relationship CourseOffering — what is being taught.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function courseOffering(): BelongsTo {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * Relationship Semester — a mirrored column, guarded by a composite FK.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Relationship Section — the cohort that sits in this meeting.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function section(): BelongsTo {
        return $this->belongsTo(Section::class);
    }

    /**
     * Relationship Instructor — the authoritative teacher for THIS meeting,
     * which may differ from the offering's proposed instructor (a lab is often
     * taken by an assistant).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instructor(): BelongsTo {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Relationship Room
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function room(): BelongsTo {
        return $this->belongsTo(Room::class);
    }

    /**
     * Relationship ScheduleGenerationRun — which run produced this row, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function generationRun(): BelongsTo {
        return $this->belongsTo(ScheduleGenerationRun::class, 'generation_run_id');
    }

    /**
     * Relationship LookupValue — draft / published / cancelled. A semantic
     * relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    /**
     * Relationship LookupValue — lecture / lab / tutorial / seminar / practical.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sessionType(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'session_type_lookup_value_id');
    }

    /**
     * Relationship User — who created this meeting.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Relationship User — who published it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function publishedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    /**
     * A readable label: "CS101 — Introduction to Computing (Monday 08:00–09:30)".
     *
     * @return string
     */
    public function displayLabel(): string {
        $offering = $this->courseOffering?->displayLabel();
        $slot = trim(DayOfWeek::typeNames($this->day_of_week) . ' ' . $this->timeRange());

        return trim($offering ? $offering . ' (' . $slot . ')' : $slot);
    }

    /**
     * The meeting's slot as "08:00–09:30". PostgreSQL hands a `time` column back
     * as "08:00:00"; timetables print minutes.
     *
     * @return string
     */
    public function timeRange(): string {
        return $this->shortTime($this->start_time) . '–' . $this->shortTime($this->end_time);
    }

    /**
     * Whether this meeting is still editable — only drafts are.
     *
     * @return bool
     */
    public function isDraft(): bool {
        return $this->status?->code === CLASS_SCHEDULE_STATUS_DRAFT;
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
            Field::uuid(),
            Field::name(fn ($data) => $data->displayLabel()),
            Field::courseOfferingId()->asInt(),
            Field::semesterId()->asInt(),
            Field::sectionId()->asInt(),
            Field::instructorId()->asInt(),
            Field::roomId()->asInt(),
            Field::sessionTypeLookupValueId()->asInt(),
            Field::statusLookupValueId()->asInt(),
            Field::generationRunId()->asInt(),
            Field::dayOfWeek()->asInt(),
            Field::state()->asInt(),
            Field::startTime(fn ($data) => $data->shortTime($data->start_time)),
            Field::endTime(fn ($data) => $data->shortTime($data->end_time)),
            Field::timeRange(fn ($data) => $data->timeRange()),
            // The status chip reads `status_code` + the lookup's own colour.
            Field::statusCode('status.code'),
            Field::sessionTypeCode('sessionType.code'),
            Field::makeType('day_of_week_data', 'day_of_week', typeClass: DayOfWeek::class, typeFunction: 'getFullTypeUsingId'),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('session_type', 'sessionType', fields: 'idAndNameFields'),
            Field::makeResource('course_offering', 'courseOffering', fields: 'idAndNameFields'),
            Field::makeResource('semester', fields: 'idAndNameFields'),
            Field::makeResource('section', fields: 'idAndNameFields'),
            Field::makeResource('instructor', fields: 'idAndNameFields'),
            Field::makeResource('room', fields: 'idAndNameFields'),
            Field::makeResource('created_by', 'createdBy', fields: 'idAndNameFields'),
            Field::makeResource('published_by', 'publishedBy', fields: 'idAndNameFields'),
            Field::publishedAt(fn ($data) => $data->published_at?->format(DATETIME_FORMAT)),
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
            Field::name(fn ($data) => $data->displayLabel()),
        ];
    }
}
