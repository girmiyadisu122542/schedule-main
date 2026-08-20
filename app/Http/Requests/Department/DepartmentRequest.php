<?php

namespace App\Http\Requests\Department;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\Physical\Room;
use App\Models\User;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class DepartmentRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateDepartment()
            : $this->userCanCreateDepartment();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:' . MAX_NAME_LENGTH],
            'code' => [
                'nullable',
                'string',
                'max:' . MAX_CAMPUS_CODE_LENGTH,
                Department::unique('code', $this->route('id')),
            ],
            'college_id' => ['required', 'integer', College::exists()],
            'head_user_id' => ['nullable', 'integer', User::exists()],
            'is_active' => ['nullable', 'boolean'],
            // ---- the department's rooms ----
            // The whole list every time, not a delta: sending it is how a
            // department says "these are ours", so an absent key means no
            // change was submitted and an empty array means "we have none".
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', 'distinct', 'exists:' . Room::getTableName() . ',id'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('department') ?? [];
    }
}
