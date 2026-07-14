<?php

namespace App\Http\Requests\Lookup;

use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class LookupValueBulkRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    public function authorize(): bool {
        return $this->userCanCreateDynamicValue();
    }

    public function rules(): array {
        $language = getCurrentLanguage($this);
        $lookupTypeId = $this->input('lookup_type_id');

        return [
            'lookup_type_id' => ['required', LookupType::exists()],
            'values' => ['required', 'array', 'min:1', 'max:100'],
            'values.*.name' => [
                'required',
                'string',
                'max:255',
                static::makeCustomRule('unique_in_type', function ($attr, $val) use ($language, $lookupTypeId) {
                    preg_match('/values\.(\d+)\.name/', $attr, $matches);
                    $currentIndex = $matches[1] ?? null;

                    $values = $this->input('values', []);
                    foreach ($values as $index => $value) {
                        if ($index != $currentIndex && isset($value['name'])) {
                            if (strtolower($value['name']) === strtolower($val)) {
                                return false;
                            }
                        }
                    }

                    if ($lookupTypeId) {
                        $exists = LookupValue::query()
                            ->where('lookup_type_id', $lookupTypeId)
                            ->whereRaw("LOWER(name->>?) = LOWER(?)", [$language, $val])
                            ->exists();

                        if ($exists) {
                            return false;
                        }
                    }

                    return true;
                }),
            ],
            'values.*.color' => ['nullable', 'string', 'max:20'],
            'values.*.icon' => ['nullable', 'string', 'max:255'],
            'values.*.order' => ['nullable', 'integer', 'min:0'],
            'values.*.is_default' => ['boolean'],
        ];
    }
}
