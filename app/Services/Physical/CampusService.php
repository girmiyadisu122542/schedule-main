<?php

namespace App\Services\Physical;

use App\Models\Physical\Campus;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CampusService {

    /**
     * Create a campus.
     *
     * @param array $data validated request payload
     * @return \App\Models\Physical\Campus|string
     */
    public function createCampus(array $data) {
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
                        CODE_OPT_MODEL => Campus::class,
                    ],
                );
            $attributes['user_id'] = Auth::id();

            // Promoting a new principal campus demotes the incumbent — the
            // partial unique index would otherwise reject the insert.
            if ($attributes['is_main']) {
                $this->demoteCurrentMainCampus();
            }

            $campus = Campus::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $campus;
    }

    /**
     * Update a campus.
     *
     * @param \App\Models\Physical\Campus $campus
     * @param array $data validated request payload
     *
     * @return \App\Models\Physical\Campus|string
     */
    public function updateCampus(Campus $campus, array $data) {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $campus);
            if (!empty($data['code'])) {
                $attributes['code'] = $data['code'];
            }

            if ($attributes['is_main']) {
                $this->demoteCurrentMainCampus(exceptId: $campus->id);
            }

            $campus->fill($attributes);
            $campus->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $campus->refresh();
    }

    /**
     * Map a validated payload onto model attributes. The jsonb name and
     * address keep the {"en": ...} shape used across the project — on update
     * only the current-language key is replaced.
     *
     * @param array $data validated request payload
     * @param \App\Models\Physical\Campus|null $campus the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Campus $campus = null): array {
        $language = getCurrentLanguage(request());

        return [
            'name' => updateLangField($campus?->name, $language, $data['name']),
            'address' => updateLangField($campus?->address, $language, $data['address'] ?? null, canBeNull: true),
            'city' => $data['city'] ?? null,
            'is_main' => (bool) ($data['is_main'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * Clear the is_main flag from whichever live campus currently holds it.
     *
     * @param int|null $exceptId campus to leave untouched (the one being updated)
     * @return void
     */
    private function demoteCurrentMainCampus(?int $exceptId = null): void {
        Campus::query()
            ->where('is_main', true)
            ->when($exceptId, fn ($query) => $query->whereNot('id', $exceptId))
            ->update(['is_main' => false]);
    }
}
