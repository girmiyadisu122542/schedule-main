<?php

namespace App\Models\Academic;

use App\Models\Offering\CourseOffering;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'program_id',
        'academic_year_id',
        'year_level',
        'label',
        'expected_students',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Program
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function program(): BelongsTo {
        return $this->belongsTo(Program::class);
    }

    /**
     * Relationship AcademicYear
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear(): BelongsTo {
        return $this->belongsTo(AcademicYear::class);
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
     * A section has no name column — its identity is the cohort it denotes,
     * "BSc CS Year 2 Section A". Composed here so lists, dropdowns and the
     * shared confirm dialogs all read the same label.
     *
     * @return string
     */
    public function displayLabel(): string {
        // Fetch first, then coalesce. `??` uses isset() semantics, and Eloquent's
        // __isset() knows nothing about the `__localized` magic accessor — so
        // `$program?->name__localized ?? $program?->code` silently always takes
        // the fallback branch. `?:` on an already-read value has no such problem.
        $programName = $this->program?->name__localized;
        $program = $programName ?: ($this->program?->code ?? '');

        return trim($program . ' ' . __('Year') . ' ' . $this->year_level . ' - ' . $this->label);
    }

    /**
     * Relationship CourseOffering — what this cohort is offered (Final Schema.md §8).
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
            Field::label(),
            Field::name(fn ($data) => $data->displayLabel()),
            Field::programId()->asInt(),
            Field::academicYearId()->asInt(),
            Field::yearLevel()->asInt(),
            Field::expectedStudents()->asInt(),
            Field::isActive()->asBool(),
            Field::makeResource('program', fields: 'idAndNameFields'),
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
            Field::label(),
            Field::name(fn ($data) => $data->displayLabel()),
        ];
    }
}
