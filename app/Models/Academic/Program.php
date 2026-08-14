<?php

namespace App\Models\Academic;

use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends ScopedModel {
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
        'department_id',
        'degree_level_lookup_value_id',
        'study_mode_lookup_value_id',
        'duration_years',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship LookupValue — the DEGREE_LEVEL this program awards.
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function degreeLevel(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'degree_level_lookup_value_id');
    }

    /**
     * Relationship LookupValue — the STUDY_MODE this program is delivered in.
     * It decides which days and hours the generator may place it in.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function studyMode(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'study_mode_lookup_value_id');
    }

    /**
     * Relationship Section
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sections(): HasMany {
        return $this->hasMany(Section::class);
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
            Field::departmentId()->asInt(),
            Field::degreeLevelLookupValueId()->asInt(),
            Field::studyModeLookupValueId()->asInt(),
            Field::durationYears()->asInt(),
            Field::isActive()->asBool(),
            // The status chip reads `degree_level_code` + the lookup's own color.
            Field::degreeLevelCode('degreeLevel.code'),
            Field::studyModeCode('studyMode.code'),
            Field::makeResource('degree_level', 'degreeLevel', fields: 'idAndNameFields'),
            Field::makeResource('study_mode', 'studyMode', fields: 'idAndNameFields'),
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
            Field::name('name__localized'),
        ];
    }
}
