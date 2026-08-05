<?php

namespace App\Services\Physical;

use App\Models\Physical\Building;
use App\Models\Physical\Campus;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuildingService {

    /**
     * Create a building.
     *
     * @param array $data validated request payload
     * @return \App\Models\Physical\Building|string
     */
    public function createBuilding(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->campusIsActive((int) $data['campus_id'])) {
            return 'campus_is_not_active';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['code'] = !empty($data['code'])
                ? $data['code']
                : generateCode(
                    name: $data['name'],
                    format: CODE_FORMAT_UPPER_SLUG,
                    options: [
                        CODE_OPT_UNIQUE => true,
                        CODE_OPT_MODEL => Building::class,
                    ],
                );
            $attributes['user_id'] = Auth::id();

            $building = Building::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $building;
    }

    /**
     * Update a building.
     *
     * @param \App\Models\Physical\Building $building
     * @param array $data validated request payload
     *
     * @return \App\Models\Physical\Building|string
     */
    public function updateBuilding(Building $building, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->campusIsActive((int) $data['campus_id'])) {
            return 'campus_is_not_active';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $building);
            if (!empty($data['code'])) {
                $attributes['code'] = $data['code'];
            }

            $building->fill($attributes);
            $building->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $building->refresh();
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @param \App\Models\Physical\Building|null $building the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Building $building = null): array {
        $language = getCurrentLanguage(request());

        return [
            'name' => updateLangField($building?->name, $language, $data['name']),
            'campus_id' => (int) $data['campus_id'],
            'floors' => isset($data['floors']) ? (int) $data['floors'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * A building may not hang off a retired campus.
     *
     * @param int $campusId
     * @return bool
     */
    private function campusIsActive(int $campusId): bool {
        return Campus::query()->where('id', $campusId)->where('is_active', true)->exists();
    }
}
