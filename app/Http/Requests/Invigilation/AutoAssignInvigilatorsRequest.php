<?php

namespace App\Http\Requests\Invigilation;

use App\Models\Academic\Semester;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Staffing every sitting in a semester from the offered availability windows.
 */
class AutoAssignInvigilatorsRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanAssignInvigilator();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'semester_id' => ['required', 'integer', 'exists:' . Semester::getTableName() . ',id'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('invigilator_assignment') ?? [];
    }
}
