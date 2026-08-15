<?php

namespace App\Services\Offering;

use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Offering\CourseOffering;
use App\Models\Offering\CourseOfferingApproval;
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
            $this->syncAdditionalSections($offering, $data);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Illuminate\Database\QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $duplicate = self::duplicateErrorKey($exception);
            if ($duplicate) {
                return $duplicate;
            }

            throw $exception;
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $offering;
    }

    /**
     * Map the offering uniqueness constraints onto a message the user can act
     * on.
     *
     * The database is the authority on "this course is already offered to this
     * cohort this semester" — two of them, in fact, since a section-less
     * offering is covered by a partial unique instead. Without this the
     * violation surfaced as a raw 500, which is the least useful possible way to
     * say "you already entered this".
     *
     * Matched by PREFIX: PostgreSQL truncates identifiers at 63 characters, so
     * the full constraint name in the message may be clipped.
     *
     * @param \Illuminate\Database\QueryException $exception
     * @return string|null an error translation key, or null when it is not a duplicate
     */
    private static function duplicateErrorKey(\Illuminate\Database\QueryException $exception): ?string {
        $message = $exception->getMessage();

        foreach (['course_offerings_semester_id_course_id_section_id', 'course_offerings_semester_course_no_section'] as $constraint) {
            if (str_contains($message, $constraint)) {
                return 'course_offering_already_exists_for_this_cohort';
            }
        }

        return null;
    }

    /**
     * The statuses whose content the author may still change.
     *
     * A returned offering is the author's again — that is the entire point of
     * separating it from `rejected`, which is a decision that stands until a
     * registrar reopens it.
     *
     * @var array<int, string>
     */
    public const EDITABLE_STATUS_CODES = [
        COURSE_OFFERING_STATUS_DRAFT,
        COURSE_OFFERING_STATUS_RETURNED,
    ];

    /**
     * Update a course offering. Only a draft or a returned offering may be
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
        if (!in_array($offering->status?->code, self::EDITABLE_STATUS_CODES, true)) {
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
            $this->syncAdditionalSections($offering, $data);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Illuminate\Database\QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $duplicate = self::duplicateErrorKey($exception);
            if ($duplicate) {
                return $duplicate;
            }

            throw $exception;
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $offering->refresh();
    }

    /**
     * Submit an offering into the approval chain.
     *
     * ONE transaction, TWO edges: `draft|returned → submitted →
     * committee_approved`, plus the `committee`/`approved` trail row attributed
     * to the submitter. The committee leader IS the committee — asking them to
     * submit and then separately approve their own submission would be two
     * clicks recording one act.
     *
     * The intermediate `submitted` value is a legal waypoint the transition
     * graph declares, not a resting state, so it is never persisted. Both edges
     * are still checked, which is what keeps the guard honest.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return \App\Models\Offering\CourseOffering|string
     */
    public function submitOffering(CourseOffering $offering) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $offering->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, $currentCode, COURSE_OFFERING_STATUS_SUBMITTED)) {
            return 'invalid_status_transition';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_SUBMITTED, COURSE_OFFERING_STATUS_COMMITTEE_APPROVED)) {
            return 'invalid_status_transition';
        }

        // An offering with no section and no program is not something a tier can
        // act on — it names no cohort to schedule.
        if (!$offering->section_id && !$offering->program_id) {
            return 'offering_needs_a_cohort';
        }

        $committeeApprovedId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_COMMITTEE_APPROVED, needId: true);
        $committeeLevelId = LookupService::getValueByCode(APPROVAL_LEVEL, APPROVAL_LEVEL_COMMITTEE, needId: true);
        $approvedDecisionId = LookupService::getValueByCode(APPROVAL_DECISION, APPROVAL_DECISION_APPROVED, needId: true);

        if (!$committeeApprovedId || !$committeeLevelId || !$approvedDecisionId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $nextSequence = (int) CourseOfferingApproval::query()
                ->where('course_offering_id', $offering->id)
                ->max('sequence') + 1;

            CourseOfferingApproval::create([
                'course_offering_id' => $offering->id,
                'level_lookup_value_id' => $committeeLevelId,
                'decision_lookup_value_id' => $approvedDecisionId,
                'sequence' => $nextSequence,
                'acted_by_id' => Auth::id(),
                'acted_at' => now(),
                'remark' => null,
                'created_at' => now(),
            ]);

            $offering->status_lookup_value_id = $committeeApprovedId;
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
     * Put a REJECTED offering back in its author's hands.
     *
     * The one status move that is not a tier decision, and deliberately the
     * narrowest possible endpoint: it takes no target, so it can only ever
     * perform `rejected → draft`. Its predecessor was a generic
     * `change-status` that accepted any target and wrote no trail row, which
     * made it a way to walk an offering to `registrar_approved` with no
     * approvals recorded at all.
     *
     * It exists because `rejected` would otherwise be a trap: the composite
     * unique on `(semester_id, course_id, section_id)` means a declined
     * offering permanently blocks its own replacement, and `destroy()` refuses
     * anything past draft.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return \App\Models\Offering\CourseOffering|string
     */
    public function reopen(CourseOffering $offering) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $offering->status?->code;
        if ($currentCode !== COURSE_OFFERING_STATUS_REJECTED) {
            return 'only_a_rejected_offering_can_be_reopened';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, $currentCode, COURSE_OFFERING_STATUS_DRAFT)) {
            return 'invalid_status_transition';
        }

        $draftId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_DRAFT, needId: true);
        if (!$draftId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $offering->status_lookup_value_id = $draftId;
            $offering->status_changed_at = now();
            // It is a draft again, so the previous submission no longer stands.
            // The rejection itself remains in the trail permanently.
            $offering->submitted_by_id = null;
            $offering->submitted_at = null;
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
    /**
     * Replace the cross-listed cohorts on an offering (C43).
     *
     * Absent key means "not editing this", which is not the same as an empty
     * array meaning "remove them all" — a partial update that silently dropped
     * the extra sections would un-cross-list a course without saying so.
     *
     * The OWNING section is filtered out if it appears: it lives on the
     * offering itself, and listing it twice would double-count its students
     * when a room is chosen.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param array $data validated payload
     *
     * @return void
     */
    private function syncAdditionalSections(CourseOffering $offering, array $data): void {
        if (!array_key_exists('additional_section_ids', $data)) {
            return;
        }

        $sectionIds = collect($data['additional_section_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === (int) $offering->section_id)
            ->unique()
            ->values();

        $offering->additionalSections()->whereNotIn('section_id', $sectionIds)->delete();

        foreach ($sectionIds as $sectionId) {
            $existing = $offering->additionalSections()->where('section_id', $sectionId)->first();
            if ($existing) {
                continue;
            }

            $row = $offering->additionalSections()->make([
                'section_id' => $sectionId,
                // The cohort's own expected size, so the room decision counts
                // everyone who will actually be in the room.
                'expected_students' => Section::whereKey($sectionId)->value('expected_students'),
            ]);

            // `user_id` is not fillable anywhere in this codebase — it is the
            // creator, not a submitted value, so it is assigned rather than
            // taken from the payload.
            $row->user_id = Auth::id();
            $row->save();
        }
    }

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
