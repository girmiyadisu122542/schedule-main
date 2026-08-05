<?php

namespace App\Http\Requests\AcademicYear;

use App\Models\Academic\AcademicYear;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class AcademicYearRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateAcademicYear()
            : $this->userCanCreateAcademicYear();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            // The code IS the label ("2025/26") — an academic year has no name column.
            'code' => [
                'required',
                'string',
                'max:' . MAX_CAMPUS_CODE_LENGTH,
                AcademicYear::unique('code', $this->route('id')),
            ],
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
        return Message::get('academic_year') ?? [];
    }
}
