<?php

namespace App\Http\Requests\Campus;

use App\Models\Physical\Campus;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class CampusRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateCampus()
            : $this->userCanCreateCampus();
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
                Campus::unique('code', $this->route('id')),
            ],
            'address' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
            'city' => ['nullable', 'string', 'max:' . MAX_LONG_NAME_LENGTH],
            'is_main' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('campus') ?? [];
    }
}
