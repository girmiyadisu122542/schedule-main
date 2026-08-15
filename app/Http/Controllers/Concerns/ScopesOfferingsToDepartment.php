<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Offering\CourseOffering;
use App\Services\User\DepartmentScopeService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Confines the course-offering endpoints to the departments the caller owns.
 *
 * The sibling of {@see ScopesSchedulesToDepartment}, and overdue: schedules,
 * reports and invigilation were all scoped while offerings — the row every one
 * of those hangs off — were not. `GET /offerings` returned the whole
 * institution to anyone holding `see:course:offering`, and approve, update and
 * delete had no ownership check at all.
 *
 * Both halves matter and neither is optional: the list query keeps other
 * departments off the screen, and the per-row check keeps them out of reach of
 * a hand-made request. A UI-only restriction is not a restriction.
 */
trait ScopesOfferingsToDepartment {

    /**
     * Narrow an offering query to the caller's departments.
     *
     * Ownership is `course_offerings.department_id` — the offering department,
     * which is also the leading half of the existing
     * `(semester_id, department_id, status_lookup_value_id)` index, so this
     * costs nothing.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyOfferingScope(Builder $query): Builder {
        $scope = app(DepartmentScopeService::class)->departmentIds();

        // null = unrestricted. An empty array is NOT the same thing: it means
        // the user is bound to no department, and must see nothing.
        if ($scope === null) {
            return $query;
        }

        return $query->whereIn('department_id', $scope);
    }

    /**
     * Whether the caller may READ this offering.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return bool
     */
    protected function scopeAllowsOffering(CourseOffering $offering): bool {
        return app(DepartmentScopeService::class)->allows($offering->department_id);
    }

    /**
     * Whether the caller may AUTHOR for a department — create, edit, delete or
     * submit an offering in it.
     *
     * Deliberately `allows()`, not `manages()`. `managedDepartmentIds()` covers
     * heads and deans and excludes plain instructors by design, which is right
     * for "may you speak FOR this department" and wrong here: the Committee
     * Leader who writes the plan is an instructor in the department, not its
     * head. Gating authorship on `manages()` would lock out the one role whose
     * whole job this is. For an instructor `allows()` resolves to exactly their
     * own department, which is the bound we want.
     *
     * @param int|null $departmentId
     * @return bool
     */
    protected function scopeAllowsAuthoring(?int $departmentId): bool {
        return $departmentId !== null && app(DepartmentScopeService::class)->allows($departmentId);
    }

    /**
     * The authoring check for an existing row.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return bool
     */
    protected function scopeAllowsAuthoringOffering(CourseOffering $offering): bool {
        return $this->scopeAllowsAuthoring($offering->department_id ? (int) $offering->department_id : null);
    }

    /**
     * Whether the caller could act at a tier AT ALL, irrespective of any
     * particular offering.
     *
     * The row-less half of {@see self::scopeAllowsTier()}: a department head
     * qualifies for the department tier somewhere, a dean for the college tier
     * somewhere. Used to build the "awaiting me" queue, which asks which
     * STATUSES could be waiting on this user before it has any rows in hand.
     *
     * Deliberately permissive — it never decides a write. The per-row check
     * still runs on every decision.
     *
     * @param string $tier an APPROVAL_LEVEL code
     * @return bool
     */
    protected function scopeQualifiesForTier(string $tier): bool {
        $scope = app(DepartmentScopeService::class);

        return match ($tier) {
            APPROVAL_LEVEL_COMMITTEE => $scope->departmentIds() === null || $scope->departmentIds() !== [],
            APPROVAL_LEVEL_DEPARTMENT => $scope->managedDepartmentIds() === null || $scope->managedDepartmentIds() !== [],
            APPROVAL_LEVEL_COLLEGE => $scope->deanedCollegeIds() === null || $scope->deanedCollegeIds() !== [],
            APPROVAL_LEVEL_REGISTRAR => $scope->isUnrestricted(),
            default => false,
        };
    }

    /**
     * Whether the caller's position lets them act at a given approval TIER on
     * this offering.
     *
     * The tiers do not all read scope the same way, which is the whole reason
     * this is not one call to `manages()`:
     *
     *  - committee — the committee IS the department's own teaching staff, so
     *    being bound to the department is the claim. Requiring `manages()` here
     *    would mean only the head could clear a committee step.
     *  - department — speaking FOR the department: its head, or a dean covering
     *    a headless one.
     *  - college — must be DEAN of the college above it. Deliberately not
     *    `manages()`, which folds deans in with heads: that would let a head
     *    take the college decision on their own department's work, collapsing
     *    two tiers of review into one signature.
     *  - registrar — institution-wide, so only an unrestricted user qualifies.
     *
     * This answers WHOSE. Whether they hold the tier's permission key at all is
     * a separate question, asked by the caller.
     *
     * @param string $tier an APPROVAL_LEVEL code
     * @param \App\Models\Offering\CourseOffering $offering
     *
     * @return bool
     */
    protected function scopeAllowsTier(string $tier, CourseOffering $offering): bool {
        $scope = app(DepartmentScopeService::class);
        $departmentId = $offering->department_id ? (int) $offering->department_id : null;

        return match ($tier) {
            APPROVAL_LEVEL_COMMITTEE => $scope->allows($departmentId),
            APPROVAL_LEVEL_DEPARTMENT => $scope->manages($departmentId),
            APPROVAL_LEVEL_COLLEGE => $scope->leadsCollegeOf($departmentId),
            APPROVAL_LEVEL_REGISTRAR => $scope->isUnrestricted(),
            default => false,
        };
    }
}
