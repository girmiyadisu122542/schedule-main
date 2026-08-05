<?php

namespace App\Services\Academic;

use App\Models\Academic\College;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CollegeService {

    /**
     * Create a college.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\College|string
     */
    public function createCollege(array $data) {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['code'] = !empty($data['code'])
                ? $data['code']
                : generateCode(
                    name: $data['name'],
                    format: CODE_FORMAT_ABBR,
                    options: [
                        CODE_OPT_UNIQUE => true,
                        CODE_OPT_MODEL => College::class,
                    ],
                );
            $attributes['user_id'] = Auth::id();

            $college = College::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $college;
    }

    /**
     * Update a college.
     *
     * @param \App\Models\Academic\College $college
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\College|string
     */
    public function updateCollege(College $college, array $data) {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $college);
            if (!empty($data['code'])) {
                $attributes['code'] = $data['code'];
            }

            $college->fill($attributes);
            $college->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $college->refresh();
    }

    /**
     * Map a validated payload onto model attributes. On update only the
     * current-language key of the jsonb name is replaced.
     *
     * @param array $data validated request payload
     * @param \App\Models\Academic\College|null $college the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?College $college = null): array {
        $language = getCurrentLanguage(request());

        return [
            'name' => updateLangField($college?->name, $language, $data['name']),
            'dean_user_id' => $data['dean_user_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
