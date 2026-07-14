<?php

namespace App\Models\Common\Lookup;

use App\Models\User;
use App\Services\Lookup\LookupService;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Rule\CustomRule;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LookupValue extends ScopedModel {
    use SoftDeletes;

    protected $fillable = [
        'lookup_type_id', 'code', 'name', 'color', 'icon',
        'order', 'is_default', 'status_lookup_value_id', 'state', 'user_id',
    ];

    protected $casts = [
        'name' => 'array',
        'is_default' => 'boolean',
        'order' => 'integer',
    ];

    protected $protectedChildRelations = ['transitionsFrom', 'transitionsTo'];

    public function lookupType(): BelongsTo {
        return $this->belongsTo(LookupType::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function transitionsFrom(): HasMany {
        return $this->hasMany(LookupTransition::class, 'from_value_id');
    }

    public function transitionsTo(): HasMany {
        return $this->hasMany(LookupTransition::class, 'to_value_id');
    }

    public function statusLookupValue(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::code(),
            Field::state(),
            Field::icon(),
            Field::color(),
            Field::lookupTypeId(),
            Field::order()->asInt(),
            Field::isDefault()->asBool(),
            Field::statusLookupValueId(),
            Field::name('name_localized'),
            Field::displayName('name__localized'),
            Field::makeResource('user', fields: 'idAndNameFields'),
            Field::makeResource('lookup_type', 'lookupType', fields: 'idAndNameFields'),
            Field::makeResource('status_lookup_value', 'statusLookupValue', fields: 'idAndNameFields'),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
        ];
    }

    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::code(),
            Field::color(),
            Field::icon(),
            Field::order()->asInt(),
            Field::name('name_localized'),
            Field::displayName('name__localized'),
        ];
    }

    /**
     * Validation rule for status_lookup_value_id field.
     * Validates that the value exists and belongs to LOOKUP_VALUE_STATUS type.
     *
     * @param array $messages
     * @return array
     */
    public static function statusLookupValueIdRules(array $messages = []): array {
        return [
            'required',
            'integer',
            CustomRule::make(
                'lookup_value_status_exist',
                $messages,
                function ($attr, $val): bool {
                    return LookupService::exists(LOOKUP_VALUE_STATUS, (int) $val);
                }
            ),
            CustomRule::make(
                'belongs_to_lookup_value_status_type',
                $messages,
                function ($attr, $val): bool {
                    return static::query()
                        ->where('id', (int) $val)
                        ->whereHas('lookupType', function ($query) {
                            $query
                                ->applyRoleBasedQuery(
                                    permissionKey: PERMISSION_SEE_DYNAMIC_VALUE
                                )
                                ->where('code', LOOKUP_VALUE_STATUS);
                        })
                        ->exists();
                }
            ),
        ];
    }
}
