<?php

namespace App\Models\Catalogue;

use App\Models\Academic\Department;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Offering\CourseOffering;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends ScopedModel {
    use SoftDeletes;


    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        // Nullable — a course with no exam length of its own sits the study
        // mode's default, so this must stay null rather than cast to 0.
        'exam_duration_minutes' => 'integer',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'title',
        'description',
        'department_id',
        'course_type_lookup_value_id',
        'credit_hours',
        'contact_hours',
        'lecture_hours_per_week',
        'lab_hours_per_week',
        'tutorial_hours_per_week',
        'sessions_per_week',
        'exam_duration_minutes',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Department — the owning department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship LookupValue — how this course is delivered (COURSE_TYPE).
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function courseType(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'course_type_lookup_value_id');
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
     * Relationship CourseOffering — every time this course has been offered (Final Schema.md §10).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function courseOfferings(): HasMany {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * The schema names this column `title`, not `name`. `name` is emitted
     * alongside it so the shared list/dropdown/confirm components — which all
     * read `name` — keep working without special-casing courses.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::code(),
            Field::title('title__localized'),
            Field::name('title__localized'),
            Field::makeLocalized('description', useFirst: true),
            Field::departmentId()->asInt(),
            Field::courseTypeLookupValueId()->asInt(),
            Field::creditHours()->asDouble(),
            Field::contactHours()->asDouble(),
            Field::lectureHoursPerWeek()->asDouble(),
            Field::labHoursPerWeek()->asDouble(),
            Field::tutorialHoursPerWeek()->asDouble(),
            Field::sessionsPerWeek()->asInt(),
            Field::examDurationMinutes()->asInt(),
            Field::isActive()->asBool(),
            // The type chip reads `course_type_code` + the lookup's own colour.
            Field::courseTypeCode('courseType.code'),
            Field::makeResource('course_type', 'courseType', fields: 'idAndNameFields'),
            Field::makeResource('department', fields: 'idAndNameFields'),
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
            Field::code(),
            Field::name('title__localized'),
        ];
    }
}
