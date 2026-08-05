<?php

namespace App\Http\Requests\Program;

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class ProgramRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateProgram()
            : $this->userCanCreateProgram();
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
                'max:' . MAX_ROOM_CODE_LENGTH,
                Program::unique('code', $this->route('id')),
            ],
            'department_id' => ['required', 'integer', Department::exists()],
            'degree_level_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(DEGREE_LEVEL, 'invalid_degree_level'),
            ],
            'duration_years' => [
                'required',
                'integer',
                'between:' . MIN_PROGRAM_DURATION_YEARS . ',' . MAX_PROGRAM_DURATION_YEARS,
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('program') ?? [];
    }
}
