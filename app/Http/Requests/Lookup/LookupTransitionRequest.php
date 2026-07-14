<?php

namespace App\Http\Requests\Lookup;

use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use Closure;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class LookupTransitionRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    public function authorize(): bool {
        return $this->route('transition') ?? $this->route('id')
            ? $this->userCanUpdateDynamicValue()
            : $this->userCanCreateDynamicValue();
    }

    public function rules(): array {
        return [
            'lookup_type_id' => [
                'required',
                'integer',
                static::makeCustomRule(
                    'lookup_type_exist',
                    $this->lookupTypeExists()
                ),
            ],
            'from_value_id' => [
                'required',
                'integer',
                static::makeCustomRule(
                    'lookup_value_exist',
                    $this->lookupValueExists()
                ),
                static::makeCustomRule(
                    'belongs_to_lookup_type',
                    $this->belongsToLookupType()
                ),
            ],
            'to_value_id' => [
                'required',
                'integer',
                'different:from_value_id',
                static::makeCustomRule(
                    'lookup_value_exist',
                    $this->lookupValueExists()
                ),
                static::makeCustomRule(
                    'belongs_to_lookup_type',
                    $this->belongsToLookupType()
                ),
            ],
        ];
    }

    private function lookupTypeExists(): Closure {
        return function ($attr, $val) {
            return LookupType::query()
                ->applyRoleBasedQuery(
                    permissionKey: PERMISSION_SEE_DYNAMIC_VALUE
                )
                ->where('id', (int) $val)
                ->exists();
        };
    }

    private function lookupValueExists(): Closure {
        return function ($attr, $val) {
            return LookupValue::query()
                ->applyRoleBasedQuery(
                    permissionKey: PERMISSION_SEE_DYNAMIC_VALUE
                )
                ->where('id', (int) $val)
                ->exists();
        };
    }

    private function belongsToLookupType(): Closure {
        return function ($attr, $val) {
            $lookupValue = LookupValue::query()
                ->applyRoleBasedQuery(
                    permissionKey: PERMISSION_SEE_DYNAMIC_VALUE
                )
                ->where('id', (int) $val)
                ->where('lookup_type_id', (int) $this->input('lookup_type_id'))
                ->exists();

            return $lookupValue;
        };
    }
}
