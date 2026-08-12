<?php

namespace App\Models\People;

use App\Models\Academic\Department;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Models\Offering\CourseOffering;
use App\Models\Schedule\ClassSchedule;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructor extends ScopedModel {
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'full_name' => 'array',
        'can_teach' => 'boolean',
        'can_invigilate' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'employee_no',
        'full_name',
        'email',
        'phone',
        'department_id',
        'academic_rank_lookup_value_id',
        'can_teach',
        'can_invigilate',
        'max_weekly_hours',
        'is_active',
    ];

    /**
     * Relationship User — THE PERSON this instructor is, not the record creator.
     * Nullable, because the registry precedes the login account.
     *
     * Deliberately named `person()` rather than `user()`: everywhere else in the
     * codebase a `user()` relation means "who created this row", and this table
     * has no creator column at all.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship LookupValue — the ACADEMIC_RANK ladder position.
     * A semantic relation, so it keeps both its name and its explicit FK.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicRank(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'academic_rank_lookup_value_id');
    }

    /**
     * Relationship CourseOffering — offerings that propose this instructor (Final Schema.md §11).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function courseOfferings(): HasMany {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * Relationship ClassSchedule — the meetings they actually teach.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classSchedules(): HasMany {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * Relationship InvigilatorAvailability — windows the department offered for them.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invigilatorAvailabilities(): HasMany {
        return $this->hasMany(InvigilatorAvailability::class);
    }

    /**
     * Relationship ExamInvigilatorAssignment — their invigilation duties.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function examInvigilatorAssignments(): HasMany {
        return $this->hasMany(ExamInvigilatorAssignment::class);
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * `name` mirrors the localized full name so the shared list/dropdown/confirm
     * components — which all read `name` — work without special-casing.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::employeeNo(),
            Field::fullName('full_name__localized'),
            Field::name('full_name__localized'),
            Field::email(),
            Field::phone(),
            Field::academicRankLookupValueId()->asInt(),
            // The rank chip reads `academic_rank_code` + the lookup's own color.
            Field::academicRankCode('academicRank.code'),
            Field::makeResource('academic_rank', 'academicRank', fields: 'idAndNameFields'),
            Field::departmentId()->asInt(),
            Field::userId()->asInt(),
            Field::canTeach()->asBool(),
            Field::canInvigilate()->asBool(),
            Field::maxWeeklyHours()->asDouble(),
            Field::isActive()->asBool(),
            Field::makeResource('department', fields: 'idAndNameFields'),
            Field::makeResource('person', fields: 'idAndNameFields'),
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
            Field::employeeNo(),
            Field::name('full_name__localized'),
        ];
    }
}
