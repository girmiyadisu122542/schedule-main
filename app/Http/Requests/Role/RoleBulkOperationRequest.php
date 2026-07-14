<?php

namespace App\Http\Requests\Role;

use App\Models\Role\Role;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class RoleBulkOperationRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanChangeRoleState();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'role_ids.*' => ['required', 'integer'],
            'action_type' => ['required', 'string', Rule::in([ACTION_ACTIVATE, ACTION_DEACTIVATE])],
            'role_ids' => [
                'bail', 'required', 'array', 'min:1',
                static::makeCustomRule('exists', function ($attr, $value) {
                    $value = array_unique($value ?? []);
                    $rolesCount = Role::query()
                        ->applyRoleBasedQuery(permissionKey: PERMISSION_CHANGE_ROLE_STATE)
                        ->whereIn('id', $value)
                        ->count();

                    return count($value) == $rolesCount ?: Message::get('role_not_found');
                }),
            ],
        ];
    }
}
