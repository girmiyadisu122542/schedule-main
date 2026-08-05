<?php

namespace App\Models\Academic;

use App\Models\Catalogue\Course;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends ScopedModel {
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'college_id',
        'head_user_id',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship College
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function college(): BelongsTo {
        return $this->belongsTo(College::class);
    }

    /**
     * Relationship User — the head this department's approval step routes to.
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function head(): BelongsTo {
        return $this->belongsTo(User::class, 'head_user_id');
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
     * Relationship Program — the degree programs this department runs (Final Schema.md §4).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function programs(): HasMany {
        return $this->hasMany(Program::class);
    }

    /**
     * Relationship Course — the catalogue this department owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function courses(): HasMany {
        return $this->hasMany(Course::class);
    }

    /**
     * Relationship Instructor — the teaching and invigilating staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function instructors(): HasMany {
        return $this->hasMany(Instructor::class);
    }

    /**
     * Relationship CourseOffering — everything this department offers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function courseOfferings(): HasMany {
        return $this->hasMany(CourseOffering::class);
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
            Field::code(),
            Field::name('name__localized'),
            Field::collegeId()->asInt(),
            Field::headUserId()->asInt(),
            Field::isActive()->asBool(),
            Field::makeResource('college', fields: 'idAndNameFields'),
            Field::makeResource('head', fields: 'idAndNameFields'),
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
            Field::name('name__localized'),
        ];
    }
}
