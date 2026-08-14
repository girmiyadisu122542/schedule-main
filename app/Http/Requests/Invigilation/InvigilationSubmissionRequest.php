<?php

namespace App\Http\Requests\Invigilation;

use App\Models\People\Instructor;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class InvigilationSubmissionRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Whether this is the caller's OWN department is a data question, not a
     * permission one — the controller answers it with the department scope.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanRespondToInvigilationRequest();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'instructor_ids' => ['required', 'array', 'min:1'],
            'instructor_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Instructor::getTableName(), 'id')],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('invigilation_request') ?? [];
    }
}
