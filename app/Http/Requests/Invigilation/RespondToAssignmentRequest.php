<?php

namespace App\Http\Requests\Invigilation;

use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * The instructor's answer to a duty: accept it or decline it.
 *
 * The decision travels as an INVIGILATION_STATUS value id rather than a free
 * string, so a typo cannot reach the service.
 */
class RespondToAssignmentRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanRespondToInvigilatorAssignment();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'status_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(INVIGILATION_STATUS, 'invalid_invigilation_decision'),
            ],
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
