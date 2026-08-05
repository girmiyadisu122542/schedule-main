<?php

namespace App\Models\Physical;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campus extends ScopedModel {
    use SoftDeletes;


    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'address' => 'array',
        'is_main' => 'boolean',
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
        'address',
        'city',
        'is_main',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Building
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function buildings(): HasMany {
        return $this->hasMany(Building::class);
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
     * Relationship Room — every room on this campus, one hop past the building (Final Schema.md §1).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function rooms(): HasManyThrough {
        return $this->hasManyThrough(Room::class, Building::class);
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
            Field::city(),
            Field::name('name__localized'),
            Field::makeLocalized('address', useFirst: true),
            Field::isMain()->asBool(),
            Field::isActive()->asBool(),
            Field::buildingsCount()->asInt(),
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
