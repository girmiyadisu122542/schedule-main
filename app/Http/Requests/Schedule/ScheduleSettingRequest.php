<?php

namespace App\Http\Requests\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Schedule\ScheduleSetting;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class ScheduleSettingRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateScheduleSetting()
            : $this->userCanCreateScheduleSetting();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            // One grid per study mode — the table's unique index says the same
            // thing, but a 422 reads better than a constraint violation.
            'study_mode_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(STUDY_MODE, 'invalid_study_mode'),
                Rule::unique(ScheduleSetting::getTableName(), 'study_mode_lookup_value_id')
                    ->ignore($this->route('id')),
            ],
            // At least one day, or the generator has nowhere to place anything.
            'teaching_days' => ['required', 'array', 'min:1'],
            'teaching_days.*' => [
                'integer',
                'between:' . ScheduleConstant::DAY_MONDAY . ',' . ScheduleConstant::DAY_SUNDAY,
            ],
            'day_start' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'day_end' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT, 'after:day_start'],
            'period_minutes' => ['required', 'integer', 'between:15,480'],
            'break_minutes' => ['nullable', 'integer', 'between:0,120'],
            // Lunch is both-or-neither: half a window is not a break.
            'lunch_start' => [
                'nullable',
                'required_with:lunch_end',
                'date_format:' . ScheduleConstant::TIME_FORMAT,
            ],
            'lunch_end' => [
                'nullable',
                'required_with:lunch_start',
                'date_format:' . ScheduleConstant::TIME_FORMAT,
                'after:lunch_start',
            ],
            // ---- the exam half ----
            'exam_days' => ['required', 'array', 'min:1'],
            'exam_days.*' => [
                'integer',
                'between:' . ScheduleConstant::DAY_MONDAY . ',' . ScheduleConstant::DAY_SUNDAY,
            ],
            'exam_day_start' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT],
            'exam_day_end' => ['required', 'date_format:' . ScheduleConstant::TIME_FORMAT, 'after:exam_day_start'],
            'exam_duration_minutes' => ['required', 'integer', 'between:15,480'],
            'exam_gap_minutes' => ['nullable', 'integer', 'between:0,240'],
            'exam_period_days' => ['required', 'integer', 'between:1,90'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('schedule_setting') ?? [];
    }
}
