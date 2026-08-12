<?php

namespace App\Services\People;

use App\Models\Academic\Department;
use App\Models\People\Instructor;
use Constants\AppConstant;
use Illuminate\Support\Facades\DB;

class InstructorService {

    /**
     * Create an instructor.
     *
     * Note there is no creator column on this table — `user_id` is the person's
     * optional portal account, so nothing is stamped from Auth here.
     *
     * @param array $data validated request payload
     * @return \App\Models\People\Instructor|string
     */
    public function createInstructor(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $instructor = Instructor::create($this->buildAttributes($data));

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $instructor;
    }

    /**
     * Update an instructor.
     *
     * @param \App\Models\People\Instructor $instructor
     * @param array $data validated request payload
     *
     * @return \App\Models\People\Instructor|string
     */
    public function updateInstructor(Instructor $instructor, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $instructor->fill($this->buildAttributes($data, $instructor));
            $instructor->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $instructor->refresh();
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param array $data validated request payload
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(array $data): ?string {
        if (!Department::query()->where('id', (int) $data['department_id'])->where('is_active', true)->exists()) {
            return 'department_is_not_active';
        }

        // Someone who can neither teach nor invigilate has no role in the system;
        // the two flags are what make one instructor table serve both populations.
        $canTeach = (bool) ($data['can_teach'] ?? true);
        $canInvigilate = (bool) ($data['can_invigilate'] ?? true);

        if (!$canTeach && !$canInvigilate) {
            return 'instructor_needs_a_capability';
        }

        return null;
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @param \App\Models\People\Instructor|null $instructor the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Instructor $instructor = null): array {
        $language = getCurrentLanguage(request());

        return [
            'employee_no' => $data['employee_no'],
            'full_name' => updateLangField($instructor?->full_name, $language, $data['full_name']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'department_id' => (int) $data['department_id'],
            'academic_rank_lookup_value_id' => $data['academic_rank_lookup_value_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'can_teach' => (bool) ($data['can_teach'] ?? true),
            'can_invigilate' => (bool) ($data['can_invigilate'] ?? true),
            'max_weekly_hours' => $data['max_weekly_hours'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
