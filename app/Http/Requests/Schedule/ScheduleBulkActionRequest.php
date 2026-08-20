<?php

namespace App\Http\Requests\Schedule;

use App\Constants\ScheduleConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

/**
 * One lifecycle decision, applied to many schedule rows.
 *
 * Authorisation is deliberately NOT decided here. Which permission a bulk run
 * needs depends on the action it carries — publishing and deleting are not the
 * same claim — so the controller checks the right one after reading it, and
 * then re-checks scope per row.
 */
class ScheduleBulkActionRequest extends FormRequest {

    /**
     * Determine if the user is authorized to make this request.
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
            'action' => ['required', 'string', Rule::in(ScheduleConstant::BULK_ACTIONS)],
            // Bounded: a bulk run is a loop of real service calls, so an
            // unbounded list is a request that never returns.
            'schedule_ids' => [
                'required',
                'array',
                'min:1',
                'max:' . ScheduleConstant::MAX_BULK_ROWS,
            ],
            'schedule_ids.*' => ['required', 'integer', 'distinct'],
            // Only meaningful for `confirm`, which records why.
            'remark' => ['nullable', 'string', 'max:' . MAX_DESCRIPTION_LENGTH],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('schedule') ?? [];
    }
}
