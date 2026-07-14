<?php

namespace App\Http\Requests\Role;

use App\Models\Role\Role;
use App\Models\Role\UserRoleBinding;
use Carbon\Carbon;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class UserRoleBindingRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return $this->userCanAssignRoleToUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $yesterday = Carbon::yesterday()->format(DATE_FORMAT);

        return [
            'role_id' => ['required', Role::exists()],
            'binding_id' => ['nullable', 'integer', UserRoleBinding::exists()],
            'ends_at' => ['date', "after:$yesterday", DATE_FORMAT_VALIDATION_KEY],
            'starts_at' => ['required', 'date', "after:$yesterday", DATE_FORMAT_VALIDATION_KEY],
        ];
    }
}
