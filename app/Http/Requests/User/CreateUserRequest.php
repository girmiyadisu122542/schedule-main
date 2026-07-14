<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Models\User\UserDetail;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Helper\Type\Gender\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class CreateUserRequest extends FormRequest {
    use PermissionAction, CustomRuleFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return $this->userCanCreateUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $ignoreId = $this->route('user') ?? $this->input('id');

        $ignoreDetailId = null;
        if ($ignoreId) {
            $ignoreDetailId = User::query()
                ->where('id', $ignoreId)
                ->value('user_detail_id');
        }

        return [
            'id' => ['nullable', 'integer', User::exists('id')],
            'gender' => ['required', Gender::ruleIn()],
            'national_id' => [
                'string',
                UserDetail::unique('national_id', $ignoreDetailId),
                ...nationalIdValidation(),
            ],
            'email' => [
                'required',
                'email',
                User::unique('email', $ignoreId),
                UserDetail::unique('email', $ignoreDetailId),
            ],
            'phone' => [
                'required',
                User::unique('phone', $ignoreId),
                UserDetail::unique('phone', $ignoreDetailId),
                ...phoneNumberValidation(),
            ],
            'last_name' => ['required', 'min:' . MIN_NAME_LENGTH, 'max:' . MAX_NAME_LENGTH,],
            'first_name' => ['required', 'min:' . MIN_NAME_LENGTH, 'max:' . MAX_NAME_LENGTH,],
            'middle_name' => ['required', 'min:' . MIN_NAME_LENGTH, 'max:' . MAX_NAME_LENGTH,],
            'birth_date' => ['nullable', 'date', 'before:today', DATE_FORMAT_VALIDATION_KEY],
            'bio' => ['nullable', 'min:' . MIN_DESCRIPTION_LENGTH, 'max:' . MAX_DESCRIPTION_LENGTH,],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array {
        return Message::get('user');
    }
}
