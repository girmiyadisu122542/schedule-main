<?php

namespace App\Services\Academic;

use App\Models\Academic\Program;
use App\Models\Academic\Section;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SectionService {

    /**
     * Create a section.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\Section|string
     */
    public function createSection(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['user_id'] = Auth::id();

            $section = Section::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $section;
    }

    /**
     * Update a section.
     *
     * @param \App\Models\Academic\Section $section
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\Section|string
     */
    public function updateSection(Section $section, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $section->fill($this->buildAttributes($data));
            $section->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $section->refresh();
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param array $data validated request payload
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(array $data): ?string {
        $program = Program::find((int) $data['program_id']);

        if (!$program?->is_active) {
            return 'program_is_not_active';
        }

        // A "Year 5" section under a 4-year program is a data-entry slip, not a
        // legitimate cohort — the CHECK only bounds it at 1..10 globally.
        if ((int) $data['year_level'] > (int) $program->duration_years) {
            return 'year_level_exceeds_program_duration';
        }

        return null;
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @return array
     */
    private function buildAttributes(array $data): array {
        return [
            'program_id' => (int) $data['program_id'],
            'academic_year_id' => (int) $data['academic_year_id'],
            'year_level' => (int) $data['year_level'],
            'label' => $data['label'],
            'expected_students' => (int) ($data['expected_students'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
