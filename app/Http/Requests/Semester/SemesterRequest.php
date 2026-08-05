<?php

namespace App\Http\Requests\Semester;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Semester;
use Constants\AppConstant;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class SemesterRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateSemester()
            : $this->userCanCreateSemester();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `status_lookup_value_id` is deliberately absent — a semester's status is a
     * guarded lifecycle and moves only through POST /semesters/{id}/change-status.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'academic_year_id' => ['required', 'integer', AcademicYear::exists()],
            'term' => [
                'required',
                'integer',
                'between:' . MIN_SEMESTER_TERM . ',' . MAX_SEMESTER_TERM,
                // One "Semester 1" per academic year — mirrors the composite unique.
                // The connection prefix is required: models live on the schedule
                // connection, and a bare table name would resolve on the default one.
                Rule::unique(AppConstant::SCHEDULE_DATABASE_CONNECTION . '.' . Semester::getTableName(), 'term')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->ignore($this->route('id')),
            ],
            'name' => ['nullable', 'string', 'max:' . MAX_NAME_LENGTH],
            'start_date' => ['required', DATE_FORMAT_VALIDATION_KEY],
            'end_date' => ['required', DATE_FORMAT_VALIDATION_KEY, 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('semester') ?? [];
    }
}
