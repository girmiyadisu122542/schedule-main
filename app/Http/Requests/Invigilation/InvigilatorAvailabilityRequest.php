<?php

namespace App\Http\Requests\Invigilation;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\People\Instructor;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * The department declaring one instructor available for one window.
 *
 * There is no update action — an availability window is a statement, not a
 * record to revise: a wrong one is deleted and re-submitted. So `authorize()`
 * does not branch on `$this->route('id')` the way the CRUD requests do.
 */
class InvigilatorAvailabilityRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanSubmitInvigilatorAvailability();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'instructor_id' => ['required', 'integer', 'exists:' . Instructor::getTableName() . ',id'],
            'semester_id' => ['required', 'integer', 'exists:' . Semester::getTableName() . ',id'],
            'available_date' => ['required', DATE_FORMAT_VALIDATION_KEY],
            'start_time' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'end_time' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT, 'after:start_time'],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('invigilator_availability') ?? [];
    }
}
