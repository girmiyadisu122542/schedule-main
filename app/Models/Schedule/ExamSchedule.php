<?php

namespace App\Models\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One exam sitting — the output of exam scheduling.
 *
 * Two paths to publication: `draft → published` when nothing needs signing off,
 * or `draft → pending_confirmation → confirmed → published` when the department
 * must agree first. `lookup_transitions` declares both; nothing here does.
 *
 * `state` plays the same conflict-liveness role it does on `class_schedules`:
 * cancelling sets `status → cancelled` AND `state → STATE_INACTIVE`, which
 * frees the room and the section's slot.
 */
class ExamSchedule extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'exam_date' => 'date',
        'confirmed_at' => 'datetime',
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
        'exam_type_lookup_value_id',
        'exam_date',
        'start_time',
        'end_time',
        'room_id',
        'required_invigilators',
        'status_lookup_value_id',
        'state',
        'generation_run_id',
        'created_by_id',
        'confirmed_by_id',
        'confirmed_at',
        'confirmation_remark',
        'published_by_id',
        'published_at',
    ];

    /**
     * Relationship CourseOffering — what is being examined.
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
     * Relationship Section — the cohort sitting the exam.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function section(): BelongsTo {
        return $this->belongsTo(Section::class);
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
     * Relationship LookupValue — where the sitting sits in its lifecycle. A
     * semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    /**
     * Relationship LookupValue — midterm / final / makeup / quiz.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function examType(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'exam_type_lookup_value_id');
    }

    /**
     * Relationship User — who created this sitting.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Relationship User — the department actor who confirmed it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_id');
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
     * A readable label: "CS101 — … · Final (2026-08-20 09:00–12:00)".
     *
     * @return string
     */
    public function displayLabel(): string {
        $offering = $this->courseOffering?->displayLabel();
        $type = $this->examType?->name__localized;
        $when = trim(($this->exam_date?->format(DATE_FORMAT) ?? '') . ' ' . $this->timeRange());

        $head = trim(implode(' · ', array_filter([$offering, $type])));

        return trim($head ? $head . ' (' . $when . ')' : $when);
    }

    /**
     * The sitting's window as "09:00–12:00".
     *
     * @return string
     */
    public function timeRange(): string {
        return $this->shortTime($this->start_time) . '–' . $this->shortTime($this->end_time);
    }

    /**
     * Whether this sitting is still editable — only drafts are.
     *
     * @return bool
     */
    public function isDraft(): bool {
        return $this->status?->code === EXAM_SCHEDULE_STATUS_DRAFT;
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
     * Relationship ExamInvigilatorAssignment — who is on duty at this sitting (Final Schema.md §15).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function examInvigilatorAssignments(): HasMany {
        return $this->hasMany(ExamInvigilatorAssignment::class);
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
            Field::roomId()->asInt(),
            Field::examTypeLookupValueId()->asInt(),
            Field::statusLookupValueId()->asInt(),
            Field::generationRunId()->asInt(),
            Field::requiredInvigilators()->asInt(),
            Field::state()->asInt(),
            Field::examDate(fn ($data) => $data->exam_date?->format(DATE_FORMAT)),
            Field::startTime(fn ($data) => $data->shortTime($data->start_time)),
            Field::endTime(fn ($data) => $data->shortTime($data->end_time)),
            Field::timeRange(fn ($data) => $data->timeRange()),
            // The chips read the stable codes + the lookup values' own colours.
            Field::statusCode('status.code'),
            Field::examTypeCode('examType.code'),
            Field::confirmationRemark(),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('exam_type', 'examType', fields: 'idAndNameFields'),
            Field::makeResource('course_offering', 'courseOffering', fields: 'idAndNameFields'),
            Field::makeResource('semester', fields: 'idAndNameFields'),
            Field::makeResource('section', fields: 'idAndNameFields'),
            Field::makeResource('room', fields: 'idAndNameFields'),
            Field::makeResource('created_by', 'createdBy', fields: 'idAndNameFields'),
            Field::makeResource('confirmed_by', 'confirmedBy', fields: 'idAndNameFields'),
            Field::makeResource('published_by', 'publishedBy', fields: 'idAndNameFields'),
            Field::confirmedAt(fn ($data) => $data->confirmed_at?->format(DATETIME_FORMAT)),
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
