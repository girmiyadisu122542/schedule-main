<?php

namespace App\Http\Requests\CourseOffering;

use App\Constants\ScheduleConstant;
use App\Rules\LookupValueOfType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

/**
 * One decision applied to many offerings.
 *
 * Note what is NOT here: the approval TIER. Exactly as in
 * {@see RecordApprovalRequest}, the acting tier is a function of each
 * offering's own status and is computed server-side per row. Letting a bulk
 * caller name their own tier would be the same privilege escalation the single
 * endpoint refuses, only forty rows at a time.
 */
class OfferingBulkActionRequest extends FormRequest {

    /**
     * Determine if the user is authorized to make this request.
     *
     * Deferred to the controller: which permission applies depends on the
     * action, and for an approval it depends on the tier each row is at.
     *
     * @return bool
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'submit', 'reopen'])],
            'offering_ids' => ['required', 'array', 'min:1', 'max:' . ScheduleConstant::MAX_BULK_ROWS],
            'offering_ids.*' => ['required', 'integer', 'distinct'],
            // The verdict itself — approve, reject or return — is a lookup
            // value, and is required only for an approval run.
            'decision_lookup_value_id' => [
                'required_if:action,approve',
                'nullable',
                'integer',
                new LookupValueOfType(APPROVAL_DECISION, 'invalid_approval_decision'),
            ],
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('course_offering') ?? [];
    }
}
