<?php

namespace App\Http\Requests\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\Physical\Room;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Placing one class meeting, by hand or as a correction to a generated row.
 *
 * `semester_id` and `section_id` are NOT accepted from the client: the service
 * mirrors them off the offering, and the composite foreign keys would reject
 * anything else anyway.
 */
class ClassScheduleRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateClassSchedule()
            : $this->userCanCreateClassSchedule();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'course_offering_id' => ['required', 'integer', 'exists:' . CourseOffering::getTableName() . ',id'],
            'instructor_id' => ['nullable', 'integer', 'exists:' . Instructor::getTableName() . ',id'],
            'room_id' => ['nullable', 'integer', 'exists:' . Room::getTableName() . ',id'],
            'session_type_lookup_value_id' => ['nullable', 'integer', new LookupValueOfType(SESSION_TYPE, 'invalid_session_type')],
            // ---- one meeting, or several ----
            // A course's week is normally more than one sitting — two lectures
            // and a lab, on different days — so Create takes a LIST of slots
            // and writes them in one go, rather than making a coordinator
            // reopen the same dialog four times re-picking the same course,
            // room and instructor each time.
            //
            // The single-slot fields still work on their own, so existing
            // callers and the drag-to-create path are unaffected: send either
            // `slots`, or `day_of_week` + the times.
            'slots' => ['array', 'min:1', 'max:' . ScheduleConstant::MAX_SESSIONS_PER_WEEK],
            'slots.*.day_of_week' => [
                'required_with:slots',
                'integer',
                'between:' . ScheduleConstant::DAY_MONDAY . ',' . ScheduleConstant::DAY_SUNDAY,
            ],
            'slots.*.start_time' => ['required_with:slots', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'slots.*.end_time' => [
                'required_with:slots',
                'date_format:' . ScheduleConstant::TIME_FORMAT,
                'after:slots.*.start_time',
            ],
            // Per-slot overrides. A lecture belongs in a hall and its lab in a
            // lab, so a single room for the whole batch would not survive
            // contact with a real timetable. Absent means "use the shared value
            // above".
            'slots.*.room_id' => ['nullable', 'integer', 'exists:' . Room::getTableName() . ',id'],
            'slots.*.instructor_id' => ['nullable', 'integer', 'exists:' . Instructor::getTableName() . ',id'],
            'slots.*.session_type_lookup_value_id' => [
                'nullable',
                'integer',
                new LookupValueOfType(SESSION_TYPE, 'invalid_session_type'),
            ],

            'day_of_week' => [
                'required_without:slots',
                'integer',
                'between:' . ScheduleConstant::DAY_MONDAY . ',' . ScheduleConstant::DAY_SUNDAY,
            ],
            'start_time' => ['required_without:slots', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'end_time' => [
                'required_without:slots',
                'date_format:' . ScheduleConstant::TIME_FORMAT,
                'after:start_time',
            ],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('class_schedule') ?? [];
    }
}
