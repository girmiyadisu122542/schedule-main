<?php

namespace App\Services\Catalogue;

use App\Models\Academic\Department;
use App\Models\Catalogue\Course;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseService {

    /**
     * Create a catalogue course.
     *
     * @param array $data validated request payload
     * @return \App\Models\Catalogue\Course|string
     */
    public function createCourse(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['user_id'] = Auth::id();

            $course = Course::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $course;
    }

    /**
     * Update a catalogue course.
     *
     * @param \App\Models\Catalogue\Course $course
     * @param array $data validated request payload
     *
     * @return \App\Models\Catalogue\Course|string
     */
    public function updateCourse(Course $course, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $course->fill($this->buildAttributes($data, $course));
            $course->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $course->refresh();
    }

    /**
     * Business rules the foreign keys and CHECKs cannot express.
     *
     * @param array $data validated request payload
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(array $data): ?string {
        if (!Department::query()->where('id', (int) $data['department_id'])->where('is_active', true)->exists()) {
            return 'department_is_not_active';
        }

        // The generator fans a course out into `sessions_per_week` meetings and
        // fills them from the weekly load; declaring sessions with no hours to
        // put in them produces empty rows the timetable cannot explain.
        $weeklyHours = (float) ($data['lecture_hours_per_week'] ?? 0)
            + (float) ($data['lab_hours_per_week'] ?? 0)
            + (float) ($data['tutorial_hours_per_week'] ?? 0);

        if (!empty($data['sessions_per_week']) && $weeklyHours <= 0) {
            return 'sessions_without_weekly_hours';
        }

        return null;
    }

    /**
     * Map a validated payload onto model attributes. On update only the
     * current-language key of each jsonb field is replaced.
     *
     * @param array $data validated request payload
     * @param \App\Models\Catalogue\Course|null $course the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Course $course = null): array {
        $language = getCurrentLanguage(request());

        return [
            'code' => $data['code'],
            'title' => updateLangField($course?->title, $language, $data['title']),
            'description' => updateLangField($course?->description, $language, $data['description'] ?? null, canBeNull: true),
            'department_id' => (int) $data['department_id'],
            'course_type_lookup_value_id' => (int) $data['course_type_lookup_value_id'],
            'credit_hours' => $data['credit_hours'],
            'contact_hours' => $data['contact_hours'] ?? null,
            'lecture_hours_per_week' => $data['lecture_hours_per_week'] ?? null,
            'lab_hours_per_week' => $data['lab_hours_per_week'] ?? null,
            'tutorial_hours_per_week' => $data['tutorial_hours_per_week'] ?? null,
            'sessions_per_week' => isset($data['sessions_per_week']) ? (int) $data['sessions_per_week'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
