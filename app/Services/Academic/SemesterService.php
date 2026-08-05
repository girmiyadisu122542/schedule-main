<?php

namespace App\Services\Academic;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Semester;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SemesterService {

    /**
     * Create a semester. New semesters always start at SEMESTER_STATUS `planning`
     * — the status is a guarded lifecycle, not a caller-supplied field.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\Semester|string
     */
    public function createSemester(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!AcademicYear::query()->where('id', (int) $data['academic_year_id'])->exists()) {
            return 'academic_year_not_found';
        }

        $planningId = LookupService::getValueByCode(SEMESTER_STATUS, SEMESTER_STATUS_PLANNING, needId: true);
        if (!$planningId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['status_lookup_value_id'] = $planningId;
            $attributes['user_id'] = Auth::id();

            // Promoting a new current semester demotes the incumbent — the
            // partial unique index would otherwise reject the insert.
            if ($attributes['is_current']) {
                $this->demoteCurrentSemester();
            }

            $semester = Semester::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $semester;
    }

    /**
     * Update a semester. The status is deliberately NOT updatable here — it
     * moves only through `changeStatus()`, which enforces `lookup_transitions`.
     *
     * @param \App\Models\Academic\Semester $semester
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\Semester|string
     */
    public function updateSemester(Semester $semester, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!AcademicYear::query()->where('id', (int) $data['academic_year_id'])->exists()) {
            return 'academic_year_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $semester);

            if ($attributes['is_current']) {
                $this->demoteCurrentSemester(exceptId: $semester->id);
            }

            $semester->fill($attributes);
            $semester->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $semester->refresh();
    }

    /**
     * Move a semester to a new SEMESTER_STATUS, but only along an edge the
     * lookup engine declares legal.
     *
     * This is the canonical guarded-status move for the whole system — course
     * offerings, class schedules and exam schedules all repeat this shape.
     *
     * @param \App\Models\Academic\Semester $semester
     * @param int $targetStatusId the SEMESTER_STATUS lookup value to move to
     *
     * @return \App\Models\Academic\Semester|string
     */
    public function changeStatus(Semester $semester, int $targetStatusId) {
        // ---- pre-flight checks (NO writes yet) ----
        if ($semester->status_lookup_value_id === $targetStatusId) {
            return 'nothing_is_changed';
        }

        $currentCode = $semester->status?->code;
        $targetCode = LookupService::getValueById($targetStatusId)?->code;

        if (!$currentCode || !$targetCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(SEMESTER_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $semester->status_lookup_value_id = $targetStatusId;
            $semester->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $semester->refresh();
    }

    /**
     * Map a validated payload onto model attributes. `name` is optional here —
     * a semester falls back to "2025/26 - Semester 2" when it has none.
     *
     * @param array $data validated request payload
     * @param \App\Models\Academic\Semester|null $semester the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Semester $semester = null): array {
        $language = getCurrentLanguage(request());

        return [
            'academic_year_id' => (int) $data['academic_year_id'],
            'term' => (int) $data['term'],
            'name' => updateLangField($semester?->name, $language, $data['name'] ?? null, canBeNull: true),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_current' => (bool) ($data['is_current'] ?? false),
        ];
    }

    /**
     * Clear the is_current flag from whichever semester currently holds it.
     *
     * @param int|null $exceptId semester to leave untouched (the one being updated)
     * @return void
     */
    private function demoteCurrentSemester(?int $exceptId = null): void {
        Semester::query()
            ->where('is_current', true)
            ->when($exceptId, fn ($query) => $query->whereNot('id', $exceptId))
            ->update(['is_current' => false]);
    }
}
