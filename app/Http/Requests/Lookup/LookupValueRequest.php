<?php

namespace App\Http\Requests\Lookup;

use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class LookupValueRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    public function authorize(): bool {
        return $this->route('value') ?? $this->route('id')
            ? $this->userCanUpdateDynamicValue()
            : $this->userCanCreateDynamicValue();
    }

    public function rules(): array {
        $ignoreId = $this->route('value') ?? $this->route('id');
        $language = getCurrentLanguage($this);
        $lookupTypeId = $this->input('lookup_type_id');

        return [
            'is_default' => ['boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'lookup_type_id' => ['required', LookupType::exists()],
            'status_lookup_value_id' => ['nullable', LookupValue::exists()],
            'name' => [
                'required',
                static::makeCustomRule('unique_in_type', function ($attr, $val) use ($ignoreId, $language, $lookupTypeId) {
                    if (!$lookupTypeId) {
                        return true;
                    }

                    $query = LookupValue::query()
                        ->where('lookup_type_id', $lookupTypeId)
                        ->jsonbLangExactValue('name', $language, $val);

                    if ($ignoreId) {
                        $query->where('id', '!=', $ignoreId);
                    }

                    return !$query->exists();
                }),
            ],
        ];
    }
}
