<?php

namespace App\Services\Offering;

use App\Models\Offering\CourseOffering;
use App\Models\Offering\CourseOfferingApproval;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfferingApprovalService {

    /**
     * Which COURSE_OFFERING_STATUS an `approved` decision produces at each tier.
     *
     * The tier ordering is not enforced here — it falls out of
     * `lookup_transitions`. A committee approval on an already
     * department-approved offering asks for `department_approved →
     * committee_approved`, which is not a declared edge, so the guard below
     * rejects it. One rule, one place.
     *
     * @var array<string, string>
     */
    private const APPROVED_STATUS_BY_LEVEL = [
        APPROVAL_LEVEL_COMMITTEE => COURSE_OFFERING_STATUS_COMMITTEE_APPROVED,
        APPROVAL_LEVEL_DEPARTMENT => COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED,
        APPROVAL_LEVEL_COLLEGE => COURSE_OFFERING_STATUS_COLLEGE_APPROVED,
        APPROVAL_LEVEL_REGISTRAR => COURSE_OFFERING_STATUS_REGISTRAR_APPROVED,
    ];

    /**
     * Record one tier's decision: append a trail row AND move the offering's
     * status, in a single transaction.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param array $data validated request payload
     *
     * @return \App\Models\Offering\CourseOfferingApproval|string
     */
    public function record(CourseOffering $offering, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $levelCode = LookupService::getValueById((int) $data['level_lookup_value_id'])?->code;
        $decisionCode = LookupService::getValueById((int) $data['decision_lookup_value_id'])?->code;
        $currentCode = $offering->status?->code;

        if (!$levelCode || !$decisionCode || !$currentCode) {
            return 'status_lookup_value_not_found';
        }

        $targetCode = $this->targetStatusFor($levelCode, $decisionCode);
        if (!$targetCode) {
            return 'invalid_approval_decision';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        $targetStatusId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, $targetCode, needId: true);
        if (!$targetStatusId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            // Serialize concurrent decisions on this offering by locking the
            // parent row — two tiers acting at once would otherwise compute the
            // same sequence. The lock goes on the offering, not on a MAX() over
            // the trail: PostgreSQL rejects FOR UPDATE alongside an aggregate.
            CourseOffering::query()->whereKey($offering->id)->lockForUpdate()->first();

            $nextSequence = (int) CourseOfferingApproval::query()
                ->where('course_offering_id', $offering->id)
                ->max('sequence') + 1;

            $approval = CourseOfferingApproval::create([
                'course_offering_id' => $offering->id,
                'level_lookup_value_id' => (int) $data['level_lookup_value_id'],
                'decision_lookup_value_id' => (int) $data['decision_lookup_value_id'],
                'sequence' => $nextSequence,
                'acted_by_id' => Auth::id(),
                'acted_at' => now(),
                'remark' => $data['remark'] ?? null,
                'created_at' => now(),
            ]);

            $offering->status_lookup_value_id = $targetStatusId;
            $offering->status_changed_at = now();
            $offering->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $approval;
    }

    /**
     * The status a decision at a given tier produces.
     *
     * `revision_requested` lands on the same `rejected` status as an outright
     * rejection — COURSE_OFFERING_STATUS has no separate value for it, and the
     * schema is deliberate about that: the status is the four-tier summary the
     * list screens filter on, while the *trail* carries why it came back. From
     * `rejected` the author reworks it to `draft` and resubmits.
     *
     * @param string $levelCode an APPROVAL_LEVEL code
     * @param string $decisionCode an APPROVAL_DECISION code
     *
     * @return string|null the target COURSE_OFFERING_STATUS code, or null when
     *                     the decision is not one this workflow understands
     */
    private function targetStatusFor(string $levelCode, string $decisionCode): ?string {
        if (in_array($decisionCode, [APPROVAL_DECISION_REJECTED, APPROVAL_DECISION_REVISION_REQUESTED], true)) {
            return COURSE_OFFERING_STATUS_REJECTED;
        }

        if ($decisionCode !== APPROVAL_DECISION_APPROVED) {
            return null;
        }

        return self::APPROVED_STATUS_BY_LEVEL[$levelCode] ?? null;
    }
}
