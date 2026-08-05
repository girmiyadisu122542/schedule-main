<?php

namespace App\Http\Requests\Room;

use App\Models\Physical\Building;
use App\Models\Physical\Room;
use App\Rules\LookupValueOfType;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Translation\Message;

class RoomRequest extends FormRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->route('id')
            ? $this->userCanUpdateRoom()
            : $this->userCanCreateRoom();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'name' => ['nullable', 'string', 'max:' . MAX_NAME_LENGTH],
            'code' => [
                'required',
                'string',
                'max:' . MAX_ROOM_CODE_LENGTH,
                Room::unique('code', $this->route('id')),
            ],
            'building_id' => ['required', 'integer', Building::exists()],
            'floor' => ['nullable', 'integer', 'between:' . MIN_BUILDING_FLOORS . ',' . MAX_BUILDING_FLOORS],
            'room_type_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(ROOM_TYPE, 'invalid_room_type'),
            ],
            'capacity' => ['required', 'integer', 'between:1,' . MAX_ROOM_CAPACITY],
            // Only meaningful on an exam venue, and required once the flag is on —
            // an exam venue with no spaced-seating figure cannot be scheduled.
            'exam_capacity' => [
                'nullable',
                'required_if:is_exam_venue,true,1',
                'integer',
                'between:1,' . MAX_ROOM_CAPACITY,
            ],
            'is_exam_venue' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('room') ?? [];
    }
}
