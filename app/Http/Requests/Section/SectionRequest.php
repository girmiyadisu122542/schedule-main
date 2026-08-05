<?php

namespace App\Http\Requests\Section;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use Constants\AppConstant;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class SectionRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateSection()
            : $this->userCanCreateSection();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'program_id' => ['required', 'integer', Program::exists()],
            'academic_year_id' => ['required', 'integer', AcademicYear::exists()],
            'year_level' => [
                'required',
                'integer',
                'between:' . MIN_SECTION_YEAR_LEVEL . ',' . MAX_SECTION_YEAR_LEVEL,
            ],
            'label' => [
                'required',
                'string',
                'max:' . MAX_SECTION_LABEL_LENGTH,
                // One "A" per program/year/level — mirrors the composite unique.
                // The connection prefix is required: models live on the schedule
                // connection, and a bare table name would resolve on the default one.
                Rule::unique(AppConstant::SCHEDULE_DATABASE_CONNECTION . '.' . Section::getTableName(), 'label')
                    ->where('program_id', $this->input('program_id'))
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('year_level', $this->input('year_level'))
                    ->ignore($this->route('id')),
            ],
            'expected_students' => ['nullable', 'integer', 'between:0,' . MAX_SECTION_EXPECTED_STUDENTS],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('section') ?? [];
    }
}
