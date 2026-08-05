<?php

namespace App\Models\Academic;

use App\Models\Common\Lookup\LookupValue;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Models\Offering\CourseOffering;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'academic_year_id',
        'term',
        'name',
        'start_date',
        'end_date',
        'status_lookup_value_id',
        'is_current',
        'user_id',
    ];

    /**
     * Relationship AcademicYear
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear(): BelongsTo {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Relationship LookupValue — the SEMESTER_STATUS this semester sits at.
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
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
     * A readable label even when the optional jsonb name is empty —
     * "2025/26 - Semester 2".
     *
     * @return string
     */
    public function displayLabel(): string {
        $localized = $this->name__localized;
        if ($localized) {
            return $localized;
        }

        return trim(($this->academicYear?->code ?? '') . ' - ' . __('Semester') . ' ' . $this->term);
    }

    /**
     * Relationship CourseOffering — everything offered this semester (Final Schema.md §7).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function courseOfferings(): HasMany {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * Relationship ScheduleGenerationRun — the generation runs executed for it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function scheduleGenerationRuns(): HasMany {
        return $this->hasMany(ScheduleGenerationRun::class);
    }

    /**
     * Relationship InvigilatorAvailability — the windows offered for its exam period.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invigilatorAvailabilities(): HasMany {
        return $this->hasMany(InvigilatorAvailability::class);
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
            Field::term()->asInt(),
            Field::name(fn ($data) => $data->displayLabel()),
            Field::academicYearId()->asInt(),
            Field::statusLookupValueId()->asInt(),
            Field::isCurrent()->asBool(),
            Field::startDate(fn ($data) => $data->start_date?->format(DATE_FORMAT)),
            Field::endDate(fn ($data) => $data->end_date?->format(DATE_FORMAT)),
            // The status chip reads `status_code` + the lookup's own colour.
            Field::statusCode('status.code'),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('academic_year', 'academicYear', fields: 'idAndNameFields'),
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
            Field::name(fn ($data) => $data->displayLabel()),
        ];
    }
}
