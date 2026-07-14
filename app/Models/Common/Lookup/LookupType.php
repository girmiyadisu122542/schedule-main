<?php

namespace App\Models\Common\Lookup;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LookupType extends ScopedModel {
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'applies_to_model',
        'is_system', 'status_lookup_value_id', 'state', 'user_id',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'applies_to_model' => 'array',
        'is_system' => 'boolean',
    ];

    protected $protectedChildRelations = ['values', 'transitions'];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function values(): HasMany {
        return $this->hasMany(LookupValue::class);
    }

    public function transitions(): HasMany {
        return $this->hasMany(LookupTransition::class);
    }

    public function statusLookupValue(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::code(),
            Field::state(),
            Field::isSystem(),
            Field::appliesToModel(),
            Field::name('name_localized'),
            Field::displayName('name__localized'),
            Field::description('description_localized'),
            Field::makeResource('user', fields: 'idAndNameFields'),
            Field::makeCollection('values', fields: 'idAndNameFields'),
            Field::makeCollection('transitions', fields: 'idAndNameFields'),
            Field::makeResource('status_lookup_value', 'statusLookupValue', fields: 'idAndNameFields'),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
        ];
    }

    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::code(),
            Field::name('name_localized'),
            Field::displayName('name__localized'),
        ];
    }
}
