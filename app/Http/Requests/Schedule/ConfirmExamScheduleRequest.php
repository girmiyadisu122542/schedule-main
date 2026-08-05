<?php

namespace App\Http\Requests\Schedule;

use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

/**
 * The department's confirmation step. One endpoint covers both halves of the
 * move — asking for confirmation (`draft → pending_confirmation`) and giving it
 * (`pending_confirmation → confirmed`) — so it carries an optional remark, the
 * department's note on what they agreed to.
 */
class ConfirmExamScheduleRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanConfirmExamSchedule();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'confirmation_remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('exam_schedule') ?? [];
    }
}
