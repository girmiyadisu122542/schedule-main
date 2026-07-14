<?php

namespace App\Http\Requests\Permission;

use App\Models\Permission\Permission;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class PermissionBulkOperationRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanChangePermissionStatus() || $this->userCanDeletePermission();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'permission_ids.*' => ['required', 'integer'],
            'action_type' => ['required', 'string', Rule::in([ACTION_ACTIVATE, ACTION_DEACTIVATE, ACTION_DELETE])],
            'permission_ids' => [
                'bail', 'required', 'array', 'min:1',
                static::makeCustomRule('exists', function ($attr, $value) {
                    $value = array_unique($value ?? []);
                    $permissionsCount = Permission::query()
                        ->whereIn('id', $value)
                        ->count();

                    return count($value) == $permissionsCount ?: Message::get('permission_not_found');
                }),
            ],
        ];
    }
}
