<?php

namespace App\Http\Requests\Schedule;

use App\Constants\ScheduleConstant;
use Helper\Permission\PermissionAction;
use Helper\Type\ScheduleType\ScheduleType;
use Helper\Type\State\State;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

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
            'name' => ['required', 'string', 'max:' . MAX_NAME_LENGTH],
            'schedule_type' => ['required', 'integer', ScheduleType::ruleIn()],
            'day_of_week' => [
                'nullable',
                'required_if:schedule_type,' . ScheduleConstant::TYPE_CLASS,
                'integer',
                'between:' . ScheduleConstant::DAY_MONDAY . ',' . ScheduleConstant::DAY_SUNDAY,
            ],
            'exam_date' => [
                'nullable',
                'required_if:schedule_type,' . ScheduleConstant::TYPE_EXAM,
                DATE_FORMAT_VALIDATION_KEY,
            ],
            'start_time' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'end_time' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT, 'after:start_time'],
            'room' => ['nullable', 'string', 'max:' . MAX_NAME_LENGTH],
            'section' => ['nullable', 'string', 'max:' . MAX_NAME_LENGTH],
            'state' => ['nullable', 'integer', State::ruleIn()],
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
