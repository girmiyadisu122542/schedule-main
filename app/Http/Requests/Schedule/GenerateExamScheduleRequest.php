<?php

namespace App\Http\Requests\Schedule;

use App\Models\Academic\Semester;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Triggering one automatic exam-scheduling run for a semester.
 *
 * `exam_type_lookup_value_id` is optional — a run generates finals unless told
 * otherwise, which is what a registrar wants nine times out of ten.
 */
class GenerateExamScheduleRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanRunExamScheduleGeneration();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'semester_id' => ['required', 'integer', 'exists:' . Semester::getTableName() . ',id'],
            'exam_type_lookup_value_id' => ['nullable', 'integer', new LookupValueOfType(EXAM_TYPE, 'invalid_exam_type')],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('schedule_generation') ?? [];
    }
}
