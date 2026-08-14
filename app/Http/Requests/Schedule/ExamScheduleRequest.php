<?php

namespace App\Http\Requests\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Placing one exam sitting, by hand or as a correction to a generated row.
 *
 * `semester_id` and `section_id` are NOT accepted from the client: the service
 * mirrors them off the offering, and the composite foreign keys would reject
 * anything else anyway.
 */
class ExamScheduleRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateExamSchedule()
            : $this->userCanCreateExamSchedule();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'course_offering_id' => ['required', 'integer', 'exists:' . CourseOffering::getTableName() . ',id'],
            'exam_type_lookup_value_id' => ['required', 'integer', new LookupValueOfType(EXAM_TYPE, 'invalid_exam_type')],
            'room_id' => ['nullable', 'integer', 'exists:' . Room::getTableName() . ',id'],
            'exam_date' => ['required', DATE_FORMAT_VALIDATION_KEY],
            'start_time' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'end_time' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT, 'after:start_time'],
            'required_invigilators' => ['nullable', 'integer', 'between:1,' . MAX_EXAM_INVIGILATORS],
            // ---- accommodations (C21) ----
            // Without a student entity this is what can honestly be offered:
            // the registrar records what is needed and reserves a second room
            // for it. The note is deliberately free text — the arrangements
            // vary too much to enumerate, and a wrong enum is worse than prose.
            'accommodation_note' => ['nullable', 'string', 'max:1000'],
            'accommodation_extra_minutes' => ['nullable', 'integer', 'between:1,240'],
            'accommodation_room_id' => ['nullable', 'integer', 'exists:' . Room::getTableName() . ',id'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('exam_schedule') ?? [];
    }
}
