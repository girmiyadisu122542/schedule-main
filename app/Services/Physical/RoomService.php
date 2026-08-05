<?php

namespace App\Services\Physical;

use App\Models\Physical\Building;
use App\Models\Physical\Room;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoomService {

    /**
     * Create a room.
     *
     * @param array $data validated request payload
     * @return \App\Models\Physical\Room|string
     */
    public function createRoom(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->buildingIsActive((int) $data['building_id'])) {
            return 'building_is_not_active';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['user_id'] = Auth::id();

            $room = Room::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $room;
    }

    /**
     * Update a room.
     *
     * @param \App\Models\Physical\Room $room
     * @param array $data validated request payload
     *
     * @return \App\Models\Physical\Room|string
     */
    public function updateRoom(Room $room, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->buildingIsActive((int) $data['building_id'])) {
            return 'building_is_not_active';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $room->fill($this->buildAttributes($data, $room));
            $room->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $room->refresh();
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @param \App\Models\Physical\Room|null $room the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Room $room = null): array {
        $language = getCurrentLanguage(request());
        $isExamVenue = (bool) ($data['is_exam_venue'] ?? false);

        return [
            'code' => $data['code'],
            'name' => updateLangField($room?->name, $language, $data['name'] ?? null, canBeNull: true),
            'building_id' => (int) $data['building_id'],
            'floor' => isset($data['floor']) ? (int) $data['floor'] : null,
            'room_type_lookup_value_id' => (int) $data['room_type_lookup_value_id'],
            'capacity' => (int) $data['capacity'],
            // Spaced-seating capacity is meaningless off an exam venue; clearing
            // it keeps the exam-venue partial index free of stale rows.
            'exam_capacity' => $isExamVenue && isset($data['exam_capacity']) ? (int) $data['exam_capacity'] : null,
            'is_exam_venue' => $isExamVenue,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * A room may not hang off a retired building.
     *
     * @param int $buildingId
     * @return bool
     */
    private function buildingIsActive(int $buildingId): bool {
        return Building::query()->where('id', $buildingId)->where('is_active', true)->exists();
    }
}
