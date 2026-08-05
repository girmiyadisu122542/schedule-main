<?php

namespace App\Services\Offering;

use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseOfferingService {

    /**
     * Create a course offering. It always starts at COURSE_OFFERING_STATUS
     * `draft` — the status is a guarded lifecycle, never a caller-supplied field.
     *
     * @param array $data validated request payload
     * @return \App\Models\Offering\CourseOffering|string
     */
    public function createOffering(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        $draftId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_DRAFT, needId: true);
        if (!$draftId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['status_lookup_value_id'] = $draftId;
            $attributes['status_changed_at'] = now();
            $attributes['created_by_id'] = Auth::id();

            $offering = CourseOffering::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $offering;
    }

    /**
     * Update a course offering. Only a draft or a rejected offering may be
     * edited — once it is in the approval chain, its content is what the tiers
     * are voting on.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param array $data validated request payload
     *
     * @return \App\Models\Offering\CourseOffering|string
     */
    public function updateOffering(CourseOffering $offering, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $editableCodes = [COURSE_OFFERING_STATUS_DRAFT, COURSE_OFFERING_STATUS_REJECTED];
        if (!in_array($offering->status?->code, $editableCodes, true)) {
            return 'offering_is_locked_for_editing';
        }

        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $offering->fill($this->buildAttributes($data));
            $offering->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $offering->refresh();
    }

    /**
     * Submit an offering into the approval chain: `draft → submitted`, stamping
     * who submitted it and when.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return \App\Models\Offering\CourseOffering|string
     */
    public function submitOffering(CourseOffering $offering) {
        // ---- pre-flight checks (NO writes yet) ----
        $submittedId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_SUBMITTED, needId: true);
        if (!$submittedId) {
            return 'status_lookup_value_not_found';
        }

        $currentCode = $offering->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, $currentCode, COURSE_OFFERING_STATUS_SUBMITTED)) {
            return 'invalid_status_transition';
        }

        // An offering with no section and no program is not something a tier can
        // act on — it names no cohort to schedule.
        if (!$offering->section_id && !$offering->program_id) {
            return 'offering_needs_a_cohort';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $offering->status_lookup_value_id = $submittedId;
            $offering->status_changed_at = now();
            $offering->submitted_by_id = Auth::id();
            $offering->submitted_at = now();
            $offering->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $offering->refresh();
    }

    /**
     * Generic guarded status move, for every edge other than submit.
     *
     * The approval tiers do NOT call this directly — they go through
     * `OfferingApprovalService::record()` (step 10), which writes the trail row
     * and the status in one transaction. This endpoint exists for the registrar
     * corrections the trail does not model.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param int $targetStatusId the COURSE_OFFERING_STATUS value to move to
     *
     * @return \App\Models\Offering\CourseOffering|string
     */
    public function changeStatus(CourseOffering $offering, int $targetStatusId) {
        // ---- pre-flight checks (NO writes yet) ----
        if ($offering->status_lookup_value_id === $targetStatusId) {
            return 'nothing_is_changed';
        }

        $currentCode = $offering->status?->code;
        $targetCode = LookupService::getValueById($targetStatusId)?->code;

        if (!$currentCode || !$targetCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $offering->status_lookup_value_id = $targetStatusId;
            $offering->status_changed_at = now();
            $offering->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $offering->refresh();
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param array $data validated request payload
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(array $data): ?string {
        // Scheduling happens per semester, and a closed semester is history.
        $semester = Semester::with('status')->find((int) $data['semester_id']);
        if ($semester?->status?->code === SEMESTER_STATUS_CLOSED) {
            return 'semester_is_closed';
        }

        if (!Course::query()->where('id', (int) $data['course_id'])->where('is_active', true)->exists()) {
            return 'course_is_not_active';
        }

        // A section belongs to an academic year; the offering belongs to a
        // semester of some year. Offering a course to a cohort from a different
        // year is a data-entry slip the FKs cannot catch.
        if (!empty($data['section_id'])) {
            $section = Section::find((int) $data['section_id']);
            if ($section && $semester && $section->academic_year_id !== $semester->academic_year_id) {
                return 'section_belongs_to_another_academic_year';
            }
        }

        // The proposed teacher must actually be allowed to teach.
        if (!empty($data['instructor_id'])) {
            $instructor = Instructor::find((int) $data['instructor_id']);
            if (!$instructor?->can_teach) {
                return 'instructor_cannot_teach';
            }
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
            'semester_id' => (int) $data['semester_id'],
            'course_id' => (int) $data['course_id'],
            'department_id' => (int) $data['department_id'],
            'program_id' => $data['program_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'instructor_id' => $data['instructor_id'] ?? null,
            'expected_students' => (int) ($data['expected_students'] ?? 0),
            'remark' => $data['remark'] ?? null,
        ];
    }
}
