<?php

namespace App\Http\Requests\Schedule;

use App\Models\Academic\Semester;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Triggering one automatic class-scheduling run for a semester.
 *
 * A generation run is not a resource the client updates, so `authorize()` does
 * not branch on `$this->route('id')` the way the CRUD requests do — there is
 * one action and one permission.
 */
class GenerateClassScheduleRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanRunClassScheduleGeneration();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'semester_id' => ['required', 'integer', 'exists:' . Semester::getTableName() . ',id'],
            // A rehearsal: report what would be placed, then leave
            // the timetable exactly as it was (C42).
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('schedule_generation') ?? [];
    }
}
