<?php

namespace App\Models\Physical;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends ScopedModel {
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
        'campus_id',
        'floors',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Campus
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function campus(): BelongsTo {
        return $this->belongsTo(Campus::class);
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
     * Relationship Room — the rooms this building contains (Final Schema.md §2).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rooms(): HasMany {
        return $this->hasMany(Room::class);
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
            Field::floors()->asInt(),
            Field::name('name__localized'),
            Field::campusId()->asInt(),
            Field::isActive()->asBool(),
            Field::makeResource('campus', fields: 'idAndNameFields'),
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
