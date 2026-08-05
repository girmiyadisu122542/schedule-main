<?php

namespace App\Http\Requests\Invigilation;

use App\Models\People\Instructor;
use App\Models\Schedule\ExamSchedule;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Putting one instructor on duty at one exam.
 *
 * The sitting's date and times are NOT accepted from the client: the service
 * mirrors them off the exam, and the composite foreign key would reject
 * anything else anyway.
 */
class AssignInvigilatorRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanAssignInvigilator();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'exam_schedule_id' => ['required', 'integer', 'exists:' . ExamSchedule::getTableName() . ',id'],
            'instructor_id' => ['required', 'integer', 'exists:' . Instructor::getTableName() . ',id'],
            'role_lookup_value_id' => ['nullable', 'integer', new LookupValueOfType(INVIGILATOR_ROLE, 'invalid_invigilator_role')],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('invigilator_assignment') ?? [];
    }
}
