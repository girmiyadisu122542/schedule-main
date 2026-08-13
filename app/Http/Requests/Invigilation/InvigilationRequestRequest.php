<?php

namespace App\Http\Requests\Invigilation;

use App\Models\Academic\Department;
use App\Models\Academic\Semester;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class InvigilationRequestRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateInvigilationRequest()
            : $this->userCanCreateInvigilationRequest();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'semester_id' => ['required', 'integer', 'exists:' . Semester::getTableName() . ',id'],
            'exam_type_lookup_value_id' => ['required', 'integer', new LookupValueOfType(EXAM_TYPE, 'invalid_exam_type')],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],

            // At least one department, each with its OWN quantity. A single
            // figure for all of them cannot express "CS 10, Accounting 4".
            'departments' => ['required', 'array', 'min:1'],
            'departments.*.department_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Department::getTableName(), 'id'),
            ],
            'departments.*.required_count' => ['required', 'integer', 'between:1,' . MAX_EXAM_INVIGILATORS],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('invigilation_request') ?? [];
    }
}
