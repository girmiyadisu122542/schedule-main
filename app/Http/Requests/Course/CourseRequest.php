<?php

namespace App\Http\Requests\Course;

use App\Models\Academic\Department;
use App\Models\Catalogue\Course;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class CourseRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateCourse()
            : $this->userCanCreateCourse();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            // The schema calls this column `title`, not `name`.
            'title' => ['required', 'string', 'max:' . MAX_LONG_NAME_LENGTH],
            'code' => [
                'required',
                'string',
                'max:' . MAX_ROOM_CODE_LENGTH,
                Course::unique('code', $this->route('id')),
            ],
            'description' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
            'department_id' => ['required', 'integer', Department::exists()],
            'course_type_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(COURSE_TYPE, 'invalid_course_type'),
            ],
            'credit_hours' => ['required', 'numeric', 'between:' . MIN_COURSE_HOURS . ',' . MAX_COURSE_HOURS],
            'contact_hours' => ['nullable', 'numeric', 'between:' . MIN_COURSE_HOURS . ',' . MAX_COURSE_HOURS],

            // Weekly load — what the class generator fans out into meetings.
            'lecture_hours_per_week' => ['nullable', 'numeric', 'between:0,' . MAX_COURSE_HOURS],
            'lab_hours_per_week' => ['nullable', 'numeric', 'between:0,' . MAX_COURSE_HOURS],
            'tutorial_hours_per_week' => ['nullable', 'numeric', 'between:0,' . MAX_COURSE_HOURS],
            'sessions_per_week' => ['nullable', 'integer', 'between:1,' . MAX_SESSIONS_PER_WEEK],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('course') ?? [];
    }
}
