<?php

namespace App\Services\Academic;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DepartmentService {

    /**
     * Create a department.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\Department|string
     */
    public function createDepartment(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->collegeIsActive((int) $data['college_id'])) {
            return 'college_is_not_active';
        }

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
                        CODE_OPT_MODEL => Department::class,
                    ],
                );
            $attributes['user_id'] = Auth::id();

            $department = Department::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $department;
    }

    /**
     * Update a department.
     *
     * @param \App\Models\Academic\Department $department
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\Department|string
     */
    public function updateDepartment(Department $department, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->collegeIsActive((int) $data['college_id'])) {
            return 'college_is_not_active';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $department);
            if (!empty($data['code'])) {
                $attributes['code'] = $data['code'];
            }

            $department->fill($attributes);
            $department->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $department->refresh();
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @param \App\Models\Academic\Department|null $department the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Department $department = null): array {
        $language = getCurrentLanguage(request());

        return [
            'name' => updateLangField($department?->name, $language, $data['name']),
            'college_id' => (int) $data['college_id'],
            'head_user_id' => $data['head_user_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * A department may not hang off a retired college.
     *
     * @param int $collegeId
     * @return bool
     */
    private function collegeIsActive(int $collegeId): bool {
        return College::query()->where('id', $collegeId)->where('is_active', true)->exists();
    }
}
