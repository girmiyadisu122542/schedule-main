<?php

namespace App\Http\Requests\CourseOffering;

use App\Rules\LookupValueOfType;
use App\Services\Lookup\LookupService;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

class RecordApprovalRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Rejecting and approving are separate permissions, so a reviewer can be
     * allowed to send work back without being allowed to advance it.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->isRejection()
            ? $this->userCanRejectCourseOffering()
            : $this->userCanApproveCourseOffering();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'level_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(APPROVAL_LEVEL, 'invalid_approval_level'),
            ],
            'decision_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(APPROVAL_DECISION, 'invalid_approval_decision'),
            ],
            // A rejection or revision request must say why. This is a
            // value-conditional rule a foreign key cannot express, which is
            // exactly why it lives here rather than in the schema.
            'remark' => [
                Rule::requiredIf(fn (): bool => $this->isRejection()),
                'nullable',
                'string',
                'max:' . MAX_DESCRIPTION_LENGTH,
            ],
        ];
    }

    /**
     * Whether the submitted decision sends the offering back.
     *
     * @return bool
     */
    private function isRejection(): bool {
        $decisionCode = LookupService::getValueById((int) $this->input('decision_lookup_value_id'))?->code;

        return in_array($decisionCode, [APPROVAL_DECISION_REJECTED, APPROVAL_DECISION_REVISION_REQUESTED], true);
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('approval') ?? [];
    }
}
