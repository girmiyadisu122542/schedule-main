<?php

namespace App\Services\Invigilation;

use App\Models\Invigilation\InvigilationRequestDepartment;
use App\Models\Invigilation\InvigilationSubmission;
use App\Models\People\Instructor;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The department's side of invigilation: answering an ask with people.
 *
 * Submitting several people is one operation, not several — a department that
 * sends four and has the third rejected should not be left having sent two.
 */
class InvigilationSubmissionService {

    /**
     * Offer people against one department's share of a request.
     *
     * @param \App\Models\Invigilation\InvigilationRequestDepartment $share
     * @param array<int, int> $instructorIds
     * @param string|null $remark
     *
     * @return \App\Models\Invigilation\InvigilationRequestDepartment|string
     */
    public function submit(InvigilationRequestDepartment $share, array $instructorIds, ?string $remark = null) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardSubmission($share, $instructorIds);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            // Serialize concurrent submissions against the same share, or two
            // clerks sending four each would together exceed the quota with
            // both having counted the same "remaining".
            InvigilationRequestDepartment::query()->whereKey($share->id)->lockForUpdate()->first();

            foreach ($instructorIds as $instructorId) {
                InvigilationSubmission::create([
                    'invigilation_request_department_id' => $share->id,
                    'instructor_id' => (int) $instructorId,
                    'submitted_by_id' => Auth::id(),
                    'submitted_at' => now(),
                    'remark' => $remark,
                ]);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $share->refresh();
    }

    /**
     * Withdraw one person a department had offered.
     *
     * A delete, not a status: an offer taken back leaves no decision trail
     * worth keeping. An assignment is different — that records somebody having
     * actually been on duty.
     *
     * @param \App\Models\Invigilation\InvigilationSubmission $submission
     * @return \App\Models\Invigilation\InvigilationRequestDepartment|string
     */
    public function withdraw(InvigilationSubmission $submission) {
        // ---- pre-flight checks (NO writes yet) ----
        $share = $submission->requestDepartment;
        if (!$share) {
            return 'invigilation_request_not_found';
        }

        if ($share->request?->status?->code !== INVIGILATION_REQUEST_STATUS_SENT) {
            return 'invigilation_request_is_not_open';
        }

        // Somebody already on duty is not the department's to take back — the
        // registrar has to release the assignment first.
        if ($this->isOnDuty($share, (int) $submission->instructor_id)) {
            return 'invigilator_is_already_on_duty';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $submission->delete();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $share->refresh();
    }

    /**
     * Everything that must hold before people are offered.
     *
     * @param \App\Models\Invigilation\InvigilationRequestDepartment $share
     * @param array<int, int> $instructorIds
     *
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardSubmission(InvigilationRequestDepartment $share, array $instructorIds): ?string {
        if (empty($instructorIds)) {
            return 'no_invigilators_selected';
        }

        // Only a sent request is open for answers: a draft has not been asked
        // yet, and a closed one is finished.
        if ($share->request?->status?->code !== INVIGILATION_REQUEST_STATUS_SENT) {
            return 'invigilation_request_is_not_open';
        }

        if (count($instructorIds) !== count(array_unique($instructorIds))) {
            return 'duplicate_invigilator_in_submission';
        }

        // Already offered against this same share — the unique index would
        // catch it, but a translated 422 reads better than a constraint error.
        $alreadySubmitted = InvigilationSubmission::query()
            ->where('invigilation_request_department_id', $share->id)
            ->whereIn('instructor_id', $instructorIds)
            ->exists();

        if ($alreadySubmitted) {
            return 'invigilator_already_submitted';
        }

        // Over-submission is refused: the registrar asked for a number, and a
        // department sending more quietly distorts what the pool contains.
        if (count($instructorIds) > $share->remainingCount()) {
            return 'submission_exceeds_required_count';
        }

        $eligible = Instructor::query()
            ->whereIn('id', $instructorIds)
            ->where('department_id', $share->department_id)
            ->where('can_invigilate', true)
            ->where('is_active', true)
            ->count();

        // A department offers its OWN people, and only those who may invigilate.
        if ($eligible !== count($instructorIds)) {
            return 'invigilator_is_not_eligible';
        }

        return null;
    }

    /**
     * Whether this instructor already has a live duty inside the request's
     * examination scope.
     *
     * @param \App\Models\Invigilation\InvigilationRequestDepartment $share
     * @param int $instructorId
     *
     * @return bool
     */
    private function isOnDuty(InvigilationRequestDepartment $share, int $instructorId): bool {
        $request = $share->request;
        if (!$request) {
            return false;
        }

        return \App\Models\Invigilation\ExamInvigilatorAssignment::query()
            ->where('instructor_id', $instructorId)
            ->where('state', STATE_ACTIVE)
            ->whereHas('examSchedule', fn ($query) => $query
                ->where('semester_id', $request->semester_id)
                ->where('exam_type_lookup_value_id', $request->exam_type_lookup_value_id))
            ->exists();
    }
}
