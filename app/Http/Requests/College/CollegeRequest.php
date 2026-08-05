<?php

namespace App\Http\Requests\College;

use App\Models\Academic\College;
use App\Models\User;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class CollegeRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateCollege()
            : $this->userCanCreateCollege();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:' . MAX_NAME_LENGTH],
            'code' => [
                'nullable',
                'string',
                'max:' . MAX_CAMPUS_CODE_LENGTH,
                College::unique('code', $this->route('id')),
            ],
            'dean_user_id' => ['nullable', 'integer', User::exists()],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('college') ?? [];
    }
}
