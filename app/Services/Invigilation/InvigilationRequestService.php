<?php

namespace App\Services\Invigilation;

use App\Models\Academic\Semester;
use App\Models\Invigilation\InvigilationRequest;
use App\Models\Invigilation\InvigilationRequestDepartment;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The registrar's side of invigilation: asking departments for people.
 *
 * A request always starts at `draft` — the status is a guarded lifecycle, never
 * a caller-supplied field — and only becomes visible to departments when it is
 * sent. Departments cannot answer a draft, which is what lets a registrar build
 * the list of asks before anybody starts responding to half of it.
 */
class InvigilationRequestService {

    /**
     * Create a request together with its per-department shares.
     *
     * The shares are written in the same transaction as the request: a request
     * with no departments is an ask of nobody, and leaving one behind after a
     * failure would show as an empty row on the registrar's list.
     *
     * @param array $data validated request payload
     * @return \App\Models\Invigilation\InvigilationRequest|string
     */
    public function createRequest(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardScope($data);
        if ($guard !== null) {
            return $guard;
        }

        $draftId = LookupService::getValueByCode(INVIGILATION_REQUEST_STATUS, INVIGILATION_REQUEST_STATUS_DRAFT, needId: true);
        if (!$draftId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $request = InvigilationRequest::create([
                'semester_id' => (int) $data['semester_id'],
                'exam_type_lookup_value_id' => (int) $data['exam_type_lookup_value_id'],
                'status_lookup_value_id' => $draftId,
                'requested_by_id' => Auth::id(),
                'remark' => $data['remark'] ?? null,
            ]);

            $this->writeShares($request, $data['departments']);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $request->refresh();
    }

    /**
     * Revise a draft request — its scope, its remark, or which departments are
     * asked and for how many.
     *
     * Only a draft: once departments can see the ask, changing the quantity
     * under them would invalidate answers already given.
     *
     * @param \App\Models\Invigilation\InvigilationRequest $request
     * @param array $data validated request payload
     *
     * @return \App\Models\Invigilation\InvigilationRequest|string
     */
    public function updateRequest(InvigilationRequest $request, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if ($request->status?->code !== INVIGILATION_REQUEST_STATUS_DRAFT) {
            return 'only_draft_invigilation_requests_can_be_edited';
        }

        $guard = $this->guardScope($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $request->fill([
                'semester_id' => (int) $data['semester_id'],
                'exam_type_lookup_value_id' => (int) $data['exam_type_lookup_value_id'],
                'remark' => $data['remark'] ?? null,
            ]);
            $request->save();

            // A draft has no submissions against it, so replacing the shares
            // wholesale cannot destroy an answer.
            $request->departments()->delete();
            $this->writeShares($request, $data['departments']);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $request->refresh();
    }

    /**
     * Send a request to its departments: `draft → sent`.
     *
     * @param \App\Models\Invigilation\InvigilationRequest $request
     * @return \App\Models\Invigilation\InvigilationRequest|string
     */
    public function send(InvigilationRequest $request) {
        return $this->moveTo($request, INVIGILATION_REQUEST_STATUS_SENT, stampSentAt: true);
    }

    /**
     * Close a request: `sent → closed`. Departments can no longer submit, and
     * whoever has already been submitted stays in the pool.
     *
     * @param \App\Models\Invigilation\InvigilationRequest $request
     * @return \App\Models\Invigilation\InvigilationRequest|string
     */
    public function close(InvigilationRequest $request) {
        return $this->moveTo($request, INVIGILATION_REQUEST_STATUS_CLOSED);
    }

    /**
     * One guarded status move, checked against `lookup_transitions` exactly as
     * every other lifecycle in the system is.
     *
     * @param \App\Models\Invigilation\InvigilationRequest $request
     * @param string $targetCode an INVIGILATION_REQUEST_STATUS code
     * @param bool $stampSentAt
     *
     * @return \App\Models\Invigilation\InvigilationRequest|string
     */
    private function moveTo(InvigilationRequest $request, string $targetCode, bool $stampSentAt = false) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $request->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(INVIGILATION_REQUEST_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        $targetId = LookupService::getValueByCode(INVIGILATION_REQUEST_STATUS, $targetCode, needId: true);
        if (!$targetId) {
            return 'status_lookup_value_not_found';
        }

        // Sending an ask that names no department asks nobody for anything.
        if ($targetCode === INVIGILATION_REQUEST_STATUS_SENT && !$request->departments()->exists()) {
            return 'invigilation_request_needs_a_department';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $request->status_lookup_value_id = $targetId;
            if ($stampSentAt) {
                $request->sent_at = now();
            }
            $request->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $request->refresh();
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param array $data validated request payload
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardScope(array $data): ?string {
        // Asking for invigilators for a semester that is over is not an ask.
        $semester = Semester::with('status')->find((int) $data['semester_id']);
        if ($semester?->status?->code === SEMESTER_STATUS_CLOSED) {
            return 'semester_is_closed';
        }

        // The Form Request already rejects a duplicated department; this
        // catches the same thing from any other caller.
        $departmentIds = array_column($data['departments'], 'department_id');
        if (count($departmentIds) !== count(array_unique($departmentIds))) {
            return 'duplicate_department_in_request';
        }

        return null;
    }

    /**
     * Write one share per department.
     *
     * @param \App\Models\Invigilation\InvigilationRequest $request
     * @param array $departments `[['department_id' => 1, 'required_count' => 4], ...]`
     *
     * @return void
     */
    private function writeShares(InvigilationRequest $request, array $departments): void {
        foreach ($departments as $share) {
            InvigilationRequestDepartment::create([
                'invigilation_request_id' => $request->id,
                'department_id' => (int) $share['department_id'],
                'required_count' => (int) $share['required_count'],
            ]);
        }
    }
}
