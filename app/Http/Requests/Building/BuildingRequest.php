<?php

namespace App\Http\Requests\Building;

use App\Models\Physical\Building;
use App\Models\Physical\Campus;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class BuildingRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateBuilding()
            : $this->userCanCreateBuilding();
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
                Building::unique('code', $this->route('id')),
            ],
            'campus_id' => ['required', 'integer', Campus::exists()],
            'floors' => ['nullable', 'integer', 'between:' . MIN_BUILDING_FLOORS . ',' . MAX_BUILDING_FLOORS],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('building') ?? [];
    }
}
