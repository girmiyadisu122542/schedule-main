<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Models\User\UserDetail;
use Helper\Permission\PermissionAction;
use Helper\Rule\CustomRuleFormRequest;
use Helper\Type\Gender\Gender;
use Helper\Validation\Phone;
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
     * Normalize the two fields whose wire form does not match their rule.
     *
     * @return void
     */
    protected function prepareForValidation(): void {
        $attributes = [];

        // The form is sent as multipart/form-data, which has no null: an
        // untouched optional input arrives as the empty string. `sometimes` then
        // reads the key as present and `digits:16` rejects it — so a user who
        // simply has no national ID could not be created at all. Restored to the
        // absent value the rules expect.
        if ($this->has('national_id') && trim((string) $this->input('national_id')) === '') {
            $attributes['national_id'] = null;
        }

        // Collapses the three accepted spellings to one before `unique` runs;
        // see Phone::normalize().
        if ($this->filled('phone')) {
            $attributes['phone'] = Phone::normalize((string) $this->input('phone'));
        }

        if ($attributes !== []) {
            $this->merge($attributes);
        }
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
            // `nullable` so a user with no national ID on file can still be
            // created; the digit rules only apply once a value is actually
            // given. Whether it is required at all is the deployment's call,
            // carried by NATIONAL_ID_IS_GLOBALLY_MANDATORY inside
            // nationalIdValidation().
            'national_id' => [
                'nullable',
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
