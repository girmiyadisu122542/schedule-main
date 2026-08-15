<?php

namespace App\Http\Requests\CourseOffering;

use App\Http\Controllers\Concerns\ScopesOfferingsToDepartment;
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
    use ScopesOfferingsToDepartment;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission AND scope, in one expression. `department_id` used to be a
     * plain field validated only with `Department::exists()`, so any holder of
     * `create:course:offering` could file an offering against a department they
     * had nothing to do with. Failing here makes that a 403 rather than a 422
     * dressed up as a validation error.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanCreateCourseOffering()
            && $this->scopeAllowsAuthoring($this->integerOrNull('department_id'));
    }

    /**
     * Read an id-shaped input, or null when it is absent or unusable.
     *
     * @param string $key
     * @return int|null
     */
    protected function integerOrNull(string $key): ?int {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : null;
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
            // ---- cross-listing (C43) ----
            // The other cohorts that attend. The OWNING section stays on
            // `section_id` and is never repeated here, so "whose offering is
            // this" keeps one answer and department scoping is untouched.
            'additional_section_ids' => ['nullable', 'array'],
            'additional_section_ids.*' => ['integer', 'distinct', 'exists:' . Section::getTableName() . ',id'],
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
