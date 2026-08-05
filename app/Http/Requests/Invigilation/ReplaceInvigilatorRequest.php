<?php

namespace App\Http\Requests\Invigilation;

use App\Models\People\Instructor;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * Swapping one invigilator for another on the same duty.
 */
class ReplaceInvigilatorRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanReplaceInvigilator();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'instructor_id' => ['required', 'integer', 'exists:' . Instructor::getTableName() . ',id'],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
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
