<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Offering\CourseOffering;
use App\Services\User\DepartmentScopeService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Confines a scheduling endpoint to the departments the caller owns.
 *
 * Permissions decide WHETHER a user may publish a schedule; this decides
 * WHOSE. A department head holding `publish:class:schedule` may publish their
 * own department's timetable and nobody else's.
 *
 * Both halves matter and neither is optional: the list query keeps other
 * departments off the screen, and the per-row check keeps them out of reach of
 * a hand-made request. A UI-only restriction is not a restriction.
 */
trait ScopesSchedulesToDepartment {

    /**
     * Narrow a schedule query to the caller's departments.
     *
     * A schedule reaches its department through the offering, which is where
     * ownership lives (Final Schema.md §12).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyDepartmentScope(Builder $query): Builder {
        $scope = app(DepartmentScopeService::class)->departmentIds();

        // null = unrestricted. An empty array is NOT the same thing: it means
        // the user is bound to no department, and must see nothing.
        if ($scope === null) {
            return $query;
        }

        return $query->whereHas('courseOffering', fn ($offering) => $offering->whereIn('department_id', $scope));
    }

    /**
     * Whether the caller may act on this schedule.
     *
     * @param \App\Models\Schedule\ClassSchedule|\App\Models\Schedule\ExamSchedule $schedule
     * @return bool
     */
    protected function scopeAllowsSchedule($schedule): bool {
        // `courseOffering` is eager-loaded on every read path that leads here;
        // the null-safe hop is for the ones that are not.
        return app(DepartmentScopeService::class)->allows($schedule->courseOffering?->department_id);
    }

    /**
     * Whether the caller may schedule against this offering.
     *
     * Used by `store`, where there is no row yet — only the offering the
     * payload names.
     *
     * @param int|null $offeringId
     * @return bool
     */
    protected function scopeAllowsOffering(?int $offeringId): bool {
        if (!$offeringId) {
            return false;
        }

        $departmentId = CourseOffering::query()->whereKey($offeringId)->value('department_id');

        return app(DepartmentScopeService::class)->allows($departmentId ? (int) $departmentId : null);
    }
}
