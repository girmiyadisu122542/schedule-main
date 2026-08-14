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
     * Determine if the user takes part in offering approval AT ALL.
     *
     * WHICH tier they may act at, and on WHOSE offering, are questions that
     * need the offering row — the controller asks those
     * (`ScopesOfferingsToDepartment::scopeAllowsTier`), and the service asks
     * them again inside the transaction. A Form Request has no business loading
     * the record to answer them.
     *
     * @return bool
     */
    public function authorize(): bool {
        if ($this->sendsBack()) {
            return $this->userCanRejectCourseOffering();
        }

        // Any of the four tier keys — `userCan()` ORs an array by default. The
        // specific tier is checked downstream, against the offering's status.
        return $this->userCan(array_values(PERMISSION_BY_APPROVAL_LEVEL));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `level_lookup_value_id` is deliberately ABSENT. The acting tier is a
     * function of the offering's current status, computed server-side. When the
     * caller could name their own tier, a department head could post
     * `level = registrar` on a college-approved offering and grant final
     * approval — so the field is not validated more strictly, it is removed.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'decision_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(APPROVAL_DECISION, 'invalid_approval_decision'),
            ],
            // A decision that sends the offering back must say why. This is a
            // value-conditional rule a foreign key cannot express, which is
            // exactly why it lives here rather than in the schema.
            'remark' => [
                Rule::requiredIf(fn (): bool => $this->sendsBack()),
                'nullable',
                'string',
                'max:' . MAX_DESCRIPTION_LENGTH,
            ],
        ];
    }

    /**
     * Whether the submitted decision sends the offering back — either returned
     * for rework or declined outright. Both need a remark, and both are gated
     * by the same key.
     *
     * @return bool
     */
    private function sendsBack(): bool {
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
