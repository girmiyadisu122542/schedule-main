<?php

namespace App\Http\Requests\Lookup;

use App\Models\Common\Lookup\LookupType;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class LookupTypeRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;
    public function authorize(): bool {
        return $this->route('type') ?? $this->route('id')
            ? $this->userCanUpdateDynamicValue()
            : $this->userCanCreateDynamicValue();
    }

    public function rules(): array {
        $ignoreId = $this->route('type') ?? $this->route('id');
        $language = getCurrentLanguage($this);

        return [
            'is_system' => ['boolean'],
            'description' => ['nullable', 'string'],
            'applies_to_model' => ['required', 'array', 'min:1'],
            'name' => [
                'required',
                LookupType::uniqueJsonBRule(
                    column: 'name',
                    message: Message::get('duplicate_lookup_type_name'),
                    language: $language,
                    ignoreId: $ignoreId
                ),
            ],
        ];
    }
}
