<?php

namespace App\Models\Academic;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
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
        'code',
        'start_date',
        'end_date',
        'is_current',
        'user_id',
    ];

    /**
     * Relationship Semester
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function semesters(): HasMany {
        return $this->hasMany(Semester::class);
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
     * An academic year has no name column — its `code` ("2025/26") is the label,
     * so `name` mirrors it and the shared list/dropdown components keep working.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::code(),
            Field::name('code'),
            Field::isCurrent()->asBool(),
            Field::startDate(fn ($data) => $data->start_date?->format(DATE_FORMAT)),
            Field::endDate(fn ($data) => $data->end_date?->format(DATE_FORMAT)),
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
            Field::name('code'),
        ];
    }
}
