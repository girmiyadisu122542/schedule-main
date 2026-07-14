<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class UserBulkOperationRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return $this->userCanchangeUserState();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'user_ids.*' => ['required', 'integer'],
            'action_type' => ['required', 'string', Rule::in(ACTIONS)],
            'user_ids' => [
                'bail', 'required', 'array', 'min:1',
                static::makeCustomRule('exists', function ($attr, $value) {
                    $value = array_unique($value ?? []);
                    $usersCount = User::query()
                        ->applyRoleBasedQuery(
                            currentUserPermission: PERMISSION_CHANGE_USER_STATE,
                            withUnassignedUsers: true,
                        )
                        ->whereIn('id', $value)
                        ->count();

                    return count($value) != $usersCount
                        ? Message::get('user_not_found')
                        : true;
                }),
            ],
        ];
    }
}
