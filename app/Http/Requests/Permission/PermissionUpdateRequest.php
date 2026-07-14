<?php

namespace App\Http\Requests\Permission;

use App\Models\Permission\Permission;
use App\Models\Permission\PermissionGroup;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class PermissionUpdateRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return $this->userCanUpdatePermission();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $ignoreId = $this->route('permissionId');

        return [
            'name' => [
                'required',
                'string',
                'max:' . MAX_NAME_LENGTH,
                Permission::uniqueJsonBRuleStrict(
                    column: 'name',
                    message: Message::get('duplicate_permission_name'),
                    ignoreId: $ignoreId
                ),
            ],
            'permission_group_id' => ['required', PermissionGroup::exists()],
            'unique_per_user' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array {
        return Message::get('permission');
    }
}
