<?php

namespace App\Models\Academic;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class College extends ScopedModel {
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
        'dean_user_id',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function departments(): HasMany {
        return $this->hasMany(Department::class);
    }

    /**
     * Relationship User — the dean this college's approval step routes to.
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dean(): BelongsTo {
        return $this->belongsTo(User::class, 'dean_user_id');
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
            Field::deanUserId()->asInt(),
            Field::isActive()->asBool(),
            Field::departmentsCount()->asInt(),
            Field::makeResource('dean', fields: 'idAndNameFields'),
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
