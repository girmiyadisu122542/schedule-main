<?php

namespace App\Services\Offering;

use App\Models\Offering\CourseOffering;
use App\Models\Offering\CourseOfferingApproval;
use App\Services\Lookup\LookupService;
use App\Services\User\DepartmentScopeService;
use Constants\AppConstant;
use Helper\Permission\PermissionAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfferingApprovalService {

    use PermissionAction;

    /**
     * Which COURSE_OFFERING_STATUS an `approved` decision produces at each tier.
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
     * Which tier is DUE to decide, given where the offering currently sits.
     *
     * The inverse of {@see self::APPROVED_STATUS_BY_LEVEL}, and the reason the
     * acting tier no longer crosses the wire: it is a function of the status,
     * not a claim the caller gets to make. When the caller named their own tier,
     * a department head could post `level = registrar` on a college-approved
     * offering and grant final approval — putting it straight into the
     * scheduling generators with three of the four signatures missing.
     *
     * @var array<string, string> status code => APPROVAL_LEVEL code
     */
    private const DUE_LEVEL_BY_STATUS = [
        COURSE_OFFERING_STATUS_SUBMITTED => APPROVAL_LEVEL_COMMITTEE,
        COURSE_OFFERING_STATUS_COMMITTEE_APPROVED => APPROVAL_LEVEL_DEPARTMENT,
        COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED => APPROVAL_LEVEL_COLLEGE,
        COURSE_OFFERING_STATUS_COLLEGE_APPROVED => APPROVAL_LEVEL_REGISTRAR,
    ];

    /**
     * The APPROVAL_LEVEL due on an offering, or null when no tier is waiting.
     *
     * Pure and static: the list endpoint stamps it on every row, the controller
     * gates on it, and this service re-checks it inside the transaction. One
     * rule, three readers, no drift — in particular the frontend must never
     * re-derive it, because two copies of a state machine is one too many.
     *
     * `draft`, `returned`, `rejected` and `registrar_approved` all map to null:
     * nobody is waiting on any of them.
     *
     * @param string|null $statusCode a COURSE_OFFERING_STATUS code
     * @return string|null an APPROVAL_LEVEL code
     */
    public static function dueLevelForStatus(?string $statusCode): ?string {
        return $statusCode === null ? null : (self::DUE_LEVEL_BY_STATUS[$statusCode] ?? null);
    }

    /**
     * The whole status → due-tier map, for callers that need to work backwards
     * from "which tiers do I hold" to "which statuses are waiting on me".
     *
     * @return array<string, string> status code => APPROVAL_LEVEL code
     */
    public static function statusesByDueLevel(): array {
        return self::DUE_LEVEL_BY_STATUS;
    }

    /**
     * Record the due tier's decision: append a trail row AND move the
     * offering's status, in a single transaction.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param array $data validated request payload
     *
     * @return \App\Models\Offering\CourseOfferingApproval|string
     */
    public function record(CourseOffering $offering, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $offering->status?->code;
        $decisionCode = LookupService::getValueById((int) $data['decision_lookup_value_id'])?->code;

        if (!$currentCode || !$decisionCode) {
            return 'status_lookup_value_not_found';
        }

        $levelCode = self::dueLevelForStatus($currentCode);
        if (!$levelCode) {
            return 'offering_is_not_awaiting_a_decision';
        }

        // Defence in depth. The controller has already asked this; asking again
        // means no future caller can reach the write path unguarded.
        if (!$this->actorMayDecide($levelCode, $offering)) {
            return 'not_your_tier_to_decide';
        }

        // Nobody countersigns their own decision. This is what makes granting a
        // dean the department tier safe: having cleared a headless department's
        // step, they cannot then take their own college step on the same
        // offering, so it still needs two real signatures.
        if ($this->lastActorId($offering) === Auth::id()) {
            return 'you_already_acted_on_this_offering';
        }

        $targetCode = $this->targetStatusFor($levelCode, $decisionCode);
        if (!$targetCode) {
            return 'invalid_approval_decision';
        }

        if (!LookupService::isTransitionAllowed(COURSE_OFFERING_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        $targetStatusId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, $targetCode, needId: true);
        $levelId = LookupService::getValueByCode(APPROVAL_LEVEL, $levelCode, needId: true);

        if (!$targetStatusId || !$levelId) {
            return 'status_lookup_value_not_found';
        }

        $approval = null;
        $isStale = false;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            // Serialize concurrent decisions by locking the parent row. The lock
            // goes on the offering, not on a MAX() over the trail: PostgreSQL
            // rejects FOR UPDATE alongside an aggregate.
            $locked = CourseOffering::query()->whereKey($offering->id)->lockForUpdate()->first();

            // The status was read before the lock was taken. If an adjacent tier
            // moved it in between, the tier this decision was computed for is no
            // longer the one due, and writing it would record a decision nobody
            // was asked for.
            $isStale = (int) $locked?->status_lookup_value_id !== (int) $offering->status_lookup_value_id;

            if (!$isStale) {
                $nextSequence = (int) CourseOfferingApproval::query()
                    ->where('course_offering_id', $offering->id)
                    ->max('sequence') + 1;

                $approval = CourseOfferingApproval::create([
                    'course_offering_id' => $offering->id,
                    // DERIVED, never from the payload.
                    'level_lookup_value_id' => $levelId,
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
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $isStale ? 'invalid_status_transition' : $approval;
    }

    /**
     * Whether the acting user may take THIS tier on THIS offering.
     *
     * Permission and scope together, because neither alone is the question:
     * holding `approve:course:offering:department` says you are a department
     * head somewhere, not that you are the head of THIS department.
     *
     * Mirrors `ScopesOfferingsToDepartment::scopeAllowsTier()`. Duplicated on
     * purpose so the service is safe called from anywhere — a console command,
     * an import, a future second controller — and not only from behind the
     * controller that currently guards it.
     *
     * @param string $levelCode an APPROVAL_LEVEL code
     * @param \App\Models\Offering\CourseOffering $offering
     *
     * @return bool
     */
    private function actorMayDecide(string $levelCode, CourseOffering $offering): bool {
        $permissionKey = PERMISSION_BY_APPROVAL_LEVEL[$levelCode] ?? null;
        if (!$permissionKey || !$this->userCan($permissionKey)) {
            return false;
        }

        $scope = app(DepartmentScopeService::class);
        $departmentId = $offering->department_id ? (int) $offering->department_id : null;

        return match ($levelCode) {
            APPROVAL_LEVEL_COMMITTEE => $scope->allows($departmentId),
            APPROVAL_LEVEL_DEPARTMENT => $scope->manages($departmentId),
            APPROVAL_LEVEL_COLLEGE => $scope->leadsCollegeOf($departmentId),
            APPROVAL_LEVEL_REGISTRAR => $scope->isUnrestricted(),
            default => false,
        };
    }

    /**
     * Who took the most recent decision on this offering.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return int|null
     */
    private function lastActorId(CourseOffering $offering): ?int {
        $actorId = CourseOfferingApproval::query()
            ->where('course_offering_id', $offering->id)
            ->orderByDesc('sequence')
            ->value('acted_by_id');

        return $actorId ? (int) $actorId : null;
    }

    /**
     * The status a decision at a given tier produces.
     *
     * A return sends the plan back to its author for rework; a rejection
     * declines it. Two different outcomes, and now two different statuses —
     * collapsing them, as this workflow did originally, meant a list could not
     * tell "fix a typo" from "we are not running this course", and left the
     * author with no way back.
     *
     * @param string $levelCode an APPROVAL_LEVEL code
     * @param string $decisionCode an APPROVAL_DECISION code
     *
     * @return string|null the target COURSE_OFFERING_STATUS code, or null when
     *                     the decision is not one this workflow understands
     */
    private function targetStatusFor(string $levelCode, string $decisionCode): ?string {
        if ($decisionCode === APPROVAL_DECISION_REVISION_REQUESTED) {
            return COURSE_OFFERING_STATUS_RETURNED;
        }

        if ($decisionCode === APPROVAL_DECISION_REJECTED) {
            return COURSE_OFFERING_STATUS_REJECTED;
        }

        if ($decisionCode !== APPROVAL_DECISION_APPROVED) {
            return null;
        }

        return self::APPROVED_STATUS_BY_LEVEL[$levelCode] ?? null;
    }
}
