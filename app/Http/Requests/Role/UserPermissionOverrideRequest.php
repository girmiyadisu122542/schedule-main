<?php

namespace App\Http\Requests\Role;

use App\Models\Permission\Permission;
use Carbon\Carbon;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class UserPermissionOverrideRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return $this->userCanAssignPermissionToUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $yesterday = Carbon::yesterday()->format(DATE_FORMAT);

        return [
            'allow' => ['required', 'boolean'],
            'ends_at' => ['date', "after:$yesterday", DATE_FORMAT_VALIDATION_KEY],
            'starts_at' => ['required', 'date', "after:$yesterday", DATE_FORMAT_VALIDATION_KEY],
            'permission_ids' => [
                'required', 'array', 'min:1', 'bail',
                static::makeCustomRule('exists', function ($attr, $value) {
                    $value = array_unique($value);
                    $permissions = Permission::query()
                        ->whereIn('id', $value)
                        ->count();

                    return $permissions == count($value);
                }),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array {
        return Message::get('user_permission_override');
    }
}
