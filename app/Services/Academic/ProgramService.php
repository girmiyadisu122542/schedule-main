<?php

namespace App\Services\Academic;

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgramService {

    /**
     * Create a program.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\Program|string
     */
    public function createProgram(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->departmentIsActive((int) $data['department_id'])) {
            return 'department_is_not_active';
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
                        CODE_OPT_MODEL => Program::class,
                    ],
                );
            $attributes['user_id'] = Auth::id();

            $program = Program::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $program;
    }

    /**
     * Update a program.
     *
     * @param \App\Models\Academic\Program $program
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\Program|string
     */
    public function updateProgram(Program $program, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->departmentIsActive((int) $data['department_id'])) {
            return 'department_is_not_active';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $program);
            if (!empty($data['code'])) {
                $attributes['code'] = $data['code'];
            }

            $program->fill($attributes);
            $program->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $program->refresh();
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @param \App\Models\Academic\Program|null $program the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Program $program = null): array {
        $language = getCurrentLanguage(request());

        return [
            'name' => updateLangField($program?->name, $language, $data['name']),
            'department_id' => (int) $data['department_id'],
            'degree_level_lookup_value_id' => (int) $data['degree_level_lookup_value_id'],
            'duration_years' => (int) $data['duration_years'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * A program may not hang off a retired department.
     *
     * @param int $departmentId
     * @return bool
     */
    private function departmentIsActive(int $departmentId): bool {
        return Department::query()->where('id', $departmentId)->where('is_active', true)->exists();
    }
}
