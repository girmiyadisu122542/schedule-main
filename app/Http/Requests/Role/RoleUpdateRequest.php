<?php

namespace App\Http\Requests\Role;

use App\Models\Role\Role;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class RoleUpdateRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return $this->userCanUpdateRole();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $roleId = $this->route('roleId');

        return [
            'unique_per_user' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'name' => [
                'sometimes',
                'string',
                'max:' . MAX_NAME_LENGTH,
                Role::uniqueJsonBRuleStrict(
                    column: 'name',
                    message: Message::get('duplicate_role_name'),
                    ignoreId: $roleId,
                ),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array {
        return Message::get('roles');
    }
}
