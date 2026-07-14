<?php

namespace Helper\Permission;

use App\Services\Lookup\LookupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait RoleBasedQuery {

    /**
     * Applies the permission based visibility query on the model.
     *
     * Flat RBAC: a user either holds the permission (through an active
     * role binding or an allow override) or does not. When the permission
     * is missing the query yields no rows; otherwise no extra constraint
     * is applied.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|null $permissionKey
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user
     * @param \Closure|null $whereCallback
     * @param \Closure|null $orWhereCallback
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApplyRoleBasedQuery(
        Builder $query,
        $permissionKey = null,
        $user = null,
        $whereCallback = null,
        $orWhereCallback = null,
    ): Builder {
        $user ??= Auth::user();

        if ($permissionKey !== null) {
            if (!$user || !$user->userCan($permissionKey)) {
                return $query->whereNull('id');
            }
        }

        if ($whereCallback) {
            $query->where($whereCallback);
        }

        if ($orWhereCallback) {
            $query->orWhere($orWhereCallback);
        }

        return $query;
    }

    /**
     * Applies the status based visibility query on any model that
     * owns a status_lookup_value_id column.
     *
     * A row is visible when it is one of:
     *  - not yet submitted for approval (null status - created by the admin user)
     *  - accepted for everyone
     *  - owned by the current user and not rejected
     *  - accepted and visible to the current user through the permission gate
     *
     *  valueIdsByCodes - fixes a different DB connection issue
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|null $permissionKey
     * @param bool $isAll
     * @param bool $includePending
     * @param int|null $state
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApplyStatusBasedQuery(
        Builder $query,
        $permissionKey = null,
        $isAll = false,
        $includePending = false,
        $state = null
    ): Builder {
        $statusValueIds = LookupService::valueIdsByCodes(
            typeCode: LOOKUP_VALUE_STATUS,
            valueCodes: [
                LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL,
                LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS,
                LOOKUP_VALUE_STATUS_PENDING,
                LOOKUP_VALUE_STATUS_REJECT,
            ]
        );

        $rejectIds = array_filter([$statusValueIds[LOOKUP_VALUE_STATUS_REJECT] ?? null]);
        $pendingIds = array_filter([$statusValueIds[LOOKUP_VALUE_STATUS_PENDING] ?? null]);
        $acceptForAllIds = array_filter([$statusValueIds[LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL] ?? null]);
        $acceptForThisIds = array_filter([$statusValueIds[LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS] ?? null]);

        if (!$isAll) {
            return $query
                ->where(function ($query) use ($permissionKey, $acceptForAllIds) {
                    $query
                        ->applyRoleBasedQuery(permissionKey: $permissionKey)
                        ->whereNotIn('status_lookup_value_id', $acceptForAllIds);
                });
        }

        if ($includePending && $isAll) {
            $acceptForAllIds = array_merge($acceptForAllIds, $pendingIds, $rejectIds);
        } else if ($includePending) {
            $acceptForAllIds = array_merge($acceptForAllIds, $pendingIds);
        }

        return $query
            ->where(function ($query) use ($permissionKey, $acceptForAllIds, $acceptForThisIds, $rejectIds) {
                $query
                    ->orWhereNull('status_lookup_value_id')
                    ->orWhereIn('status_lookup_value_id', $acceptForAllIds)
                    ->orWhere(function ($query) use ($permissionKey, $acceptForThisIds) {
                        $query
                            ->applyRoleBasedQuery(permissionKey: $permissionKey)
                            ->whereIn('status_lookup_value_id', $acceptForThisIds)
                            ->whereNot('user_id', Auth::id());
                    })
                    ->orWhere(function ($query) use ($rejectIds) {
                        $query
                            ->where('user_id', Auth::id())
                            ->whereNotIn('status_lookup_value_id', $rejectIds);
                    });
            })
           ->when(!is_null($state), fn ($query) => $query->where('state', $state));
    }
}
