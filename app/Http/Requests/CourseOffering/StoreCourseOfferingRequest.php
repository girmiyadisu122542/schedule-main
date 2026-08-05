<?php

namespace App\Http\Requests\CourseOffering;

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\People\Instructor;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class StoreCourseOfferingRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanCreateCourseOffering();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `status_lookup_value_id` is deliberately absent — an offering is created
     * at `draft` and moves only through submit / the approval trail.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'semester_id' => ['required', 'integer', Semester::exists()],
            'course_id' => ['required', 'integer', Course::exists()],
            'department_id' => ['required', 'integer', Department::exists()],
            'program_id' => ['nullable', 'integer', Program::exists()],
            'section_id' => ['nullable', 'integer', Section::exists()],
            'instructor_id' => ['nullable', 'integer', Instructor::exists()],
            'expected_students' => ['nullable', 'integer', 'between:0,' . MAX_SECTION_EXPECTED_STUDENTS],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('course_offering') ?? [];
    }
}
