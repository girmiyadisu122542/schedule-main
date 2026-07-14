<?php

namespace Helper\Permission;

use Helper\Cache\PermissionCacheHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait RoleBasedQueryForUser {

    /**
     * A query that can be called to apply the
     * roleBasedQuery() to the current user model.
     *
     * Flat RBAC: the current user must hold $currentUserPermission to see
     * any other user at all. When $otherUserPermission is provided, the
     * listed users are narrowed to those that hold one of the given
     * permissions through an active role binding or an allow override.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|string|null $user
     * @param string|null $currentUserPermission
     * @param string|array|null $otherUserPermission
     * @param bool $excludeCurrent
     * @param \Closure|null $roleBindingQuery
     * @param \Closure|null $permissionOverrideQuery
     * @param bool $withUnassignedUsers
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApplyRoleBasedQuery(
        Builder $query,
        $user = null,
        $currentUserPermission = null,
        $otherUserPermission = null,
        $excludeCurrent = true,
        $roleBindingQuery = null,
        $permissionOverrideQuery = null,
        $withUnassignedUsers = false,
    ) {
        if (is_string($user)) {
            $otherUserPermission = $currentUserPermission;
            $currentUserPermission = $user;
            $user = null;
        }

        if ($otherUserPermission !== null && !is_array($otherUserPermission)) {
            $otherUserPermission = [$otherUserPermission];
        }

        $user ??= Auth::user();
        if ($excludeCurrent) {
            $query->whereNot('id', $user->id);
        }

        if ($currentUserPermission !== null && !$user->userCan($currentUserPermission)) {
            return $query->whereNull('id');
        }

        $allowedRoleIds = [];
        if ($otherUserPermission !== null) {
            $roleCaches = PermissionCacheHandler::getRoleIdsCache();
            foreach ($roleCaches as $roleId) {
                $permissions = PermissionCacheHandler::getRolePermissionKeys($roleId);
                $foundPermission = array_intersect($otherUserPermission, $permissions);

                if (count($foundPermission) == 0) {
                    continue;
                }

                array_push($allowedRoleIds, $roleId);
            }

            if (count($allowedRoleIds) == 0) {
                return $query->whereNull('id');
            }
        }

        return $query
            ->where(function ($query) use ($user, $otherUserPermission, $allowedRoleIds, $roleBindingQuery, $permissionOverrideQuery, $withUnassignedUsers, $excludeCurrent) {
                if ($excludeCurrent === false) {
                    $query->orWhere('id', $user->id);
                }

                $query
                    ->orWhereHas('userRoleBindings', function ($query) use ($otherUserPermission, $allowedRoleIds, $roleBindingQuery) {
                        if ($otherUserPermission) {
                            $query->whereIn('role_id', $allowedRoleIds);
                        }

                        if ($roleBindingQuery) {
                            $query->where($roleBindingQuery);
                        }

                        $query->withInActiveDateRange();
                    });

                if ($otherUserPermission !== null) {
                    $query
                        ->orWhereHas('userPermissionOverrides', function ($query) use ($otherUserPermission, $permissionOverrideQuery) {
                            if ($otherUserPermission) {
                                $query->whereHas('permission', fn ($query) => $query->whereIn('key', $otherUserPermission));
                            }

                            if ($permissionOverrideQuery) {
                                $query->where($permissionOverrideQuery);
                            }

                            $query
                                ->where('allow', true)
                                ->withInActiveDateRange();
                        });
                }

                if ($withUnassignedUsers) {
                    /** @var \Helper\Model\UserModel $this */
                    if ($this->userCanSeeNotAssignedUsers()) {
                        $query
                            ->orWhere(function ($query) use ($otherUserPermission) {
                                if ($otherUserPermission) {
                                    $query->whereDoesntHave('userPermissionOverrides', fn ($query) => $query->withInActiveDateRange());
                                }

                                $query->whereDoesntHave('userRoleBindings', fn ($query) => $query->withInActiveDateRange());
                            });
                    }
                }
            });
    }
}
