<?php

namespace App\Models\Offering;

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Common\Lookup\LookupValue;
use App\Models\People\Instructor;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use App\Models\User;
use App\Services\Offering\OfferingApprovalService;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status_changed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'semester_id',
        'course_id',
        'department_id',
        'program_id',
        'section_id',
        'instructor_id',
        'expected_students',
        'status_lookup_value_id',
        'status_changed_at',
        'created_by_id',
        'submitted_by_id',
        'submitted_at',
        'remark',
    ];

    /**
     * Relationship Semester
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Relationship Course
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function course(): BelongsTo {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relationship Department — the offering department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship Program
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function program(): BelongsTo {
        return $this->belongsTo(Program::class);
    }

    /**
     * Relationship Section — the cohort this offering is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function section(): BelongsTo {
        return $this->belongsTo(Section::class);
    }

    /**
     * Relationship Instructor — the proposed teacher.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instructor(): BelongsTo {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Relationship LookupValue — position in the four-tier approval chain.
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    /**
     * Relationship User — who drafted this offering.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Relationship User — who submitted it into the approval chain.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function submittedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * Relationship CourseOfferingApproval — the append-only decision trail.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approvals(): HasMany {
        return $this->hasMany(CourseOfferingApproval::class);
    }

    /**
     * A readable label: "CS101 — BSc in Computer Science Year 2 - A".
     *
     * @return string
     */
    public function displayLabel(): string {
        $courseTitle = $this->course?->title__localized;
        $label = $this->course?->code ?? '';

        if ($courseTitle) {
            $label .= ' — ' . $courseTitle;
        }

        $section = $this->section?->displayLabel();

        return trim($section ? $label . ' (' . $section . ')' : $label);
    }

    /**
     * Relationship ClassSchedule — the weekly meetings this offering fans out into (Final Schema.md §12).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classSchedules(): HasMany {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * Relationship ExamSchedule — its exam sittings.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function examSchedules(): HasMany {
        return $this->hasMany(ExamSchedule::class);
    }

    /**
     * Relationship CourseOfferingSection — the extra cohorts that attend.
     *
     * Empty for the ordinary case. A cross-listed offering names the other
     * sections here; the owning section stays on this row's own `section_id`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function additionalSections(): HasMany {
        return $this->hasMany(CourseOfferingSection::class);
    }

    /**
     * Everyone sitting in the room: the owning cohort plus any cross-listed
     * ones. This is the number a room has to hold.
     *
     * @return int
     */
    public function totalExpectedStudents(): int {
        return (int) $this->expected_students
            + (int) $this->additionalSections->sum(fn ($extra) => (int) $extra->expected_students);
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
            Field::semesterId()->asInt(),
            Field::courseId()->asInt(),
            Field::departmentId()->asInt(),
            Field::programId()->asInt(),
            Field::sectionId()->asInt(),
            Field::instructorId()->asInt(),
            Field::expectedStudents()->asInt(),
            Field::statusLookupValueId()->asInt(),
            Field::remark(),
            // The status chip reads `status_code` + the lookup's own colour.
            Field::statusCode('status.code'),
            // The tier due to decide, computed by the same rule the approval
            // service enforces. The frontend must NOT re-derive it — a second
            // copy of the state machine in TypeScript is a second place for it
            // to drift.
            Field::make('awaiting_level_code', fn ($data) => OfferingApprovalService::dueLevelForStatus($data->status?->code)),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('semester', fields: 'idAndNameFields'),
            Field::makeResource('course', fields: 'idAndNameFields'),
            Field::makeResource('department', fields: 'idAndNameFields'),
            Field::makeResource('program', fields: 'idAndNameFields'),
            Field::makeResource('section', fields: 'idAndNameFields'),
            // The other cohorts that attend, for a cross-listed offering.
            // Empty for the ordinary case, which is most of them.
            Field::makeCollection('additional_sections', 'additionalSections', fields: 'idAndNameFields'),
            Field::make('total_expected_students', fn ($data) => $data->totalExpectedStudents()),
            Field::makeResource('instructor', fields: 'idAndNameFields'),
            Field::makeResource('created_by', 'createdBy', fields: 'idAndNameFields'),
            Field::makeResource('submitted_by', 'submittedBy', fields: 'idAndNameFields'),
            Field::submittedAt(fn ($data) => $data->submitted_at?->format(DATETIME_FORMAT)),
            Field::statusChangedAt(fn ($data) => $data->status_changed_at?->format(DATETIME_FORMAT)),
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
            // The parts, alongside the composed label. A timetable identifies a
            // course by its CODE — it prints bare on a wall chart where nobody
            // has room for the title — while a detail page wants the title and
            // a dropdown wants the whole label. Emitting all three lets each
            // screen choose instead of splitting a string apart.
            Field::courseCode('course.code'),
            Field::courseTitle('course.title__localized'),
            Field::sectionLabel(fn ($data) => $data->section?->displayLabel()),
        ];
    }
}
