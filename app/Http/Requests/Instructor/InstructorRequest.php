<?php

namespace App\Http\Requests\Instructor;

use App\Models\Academic\Department;
use App\Models\People\Instructor;
use App\Models\User;
use Constants\AppConstant;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class InstructorRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateInstructor()
            : $this->userCanCreateInstructor();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'full_name' => ['required', 'string', 'max:' . MAX_LONG_NAME_LENGTH],
            'employee_no' => [
                'required',
                'string',
                'max:' . MAX_CODE_LENGTH,
                Instructor::unique('employee_no', $this->route('id')),
            ],
            'email' => ['nullable', 'email', 'max:' . MAX_INSTRUCTOR_EMAIL_LENGTH],
            'phone' => ['nullable', 'string', 'max:' . MAX_PHONE_LENGTH],
            'department_id' => ['required', 'integer', Department::exists()],
            'academic_rank' => ['nullable', 'string', 'max:' . MAX_ACADEMIC_RANK_LENGTH],
            // The optional portal account — THE PERSON, not a creator reference.
            // One instructor row per user, so the link is unique.
            'user_id' => [
                'nullable',
                'integer',
                User::exists(),
                Rule::unique(AppConstant::SCHEDULE_DATABASE_CONNECTION . '.' . Instructor::getTableName(), 'user_id')
                    ->ignore($this->route('id')),
            ],
            'can_teach' => ['nullable', 'boolean'],
            'can_invigilate' => ['nullable', 'boolean'],
            'max_weekly_hours' => ['nullable', 'numeric', 'between:1,' . MAX_INSTRUCTOR_WEEKLY_HOURS],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('instructor') ?? [];
    }
}
