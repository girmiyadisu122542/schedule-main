<?php

namespace Helper\Cache;

use App\Models\Role\UserPermissionOverride;
use App\Models\Role\UserRoleBinding;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RoleCacheHandler {

    /**
     * These are the keys that will be used
     * to store the permission cache data
     */
    public const ENDS_AT_KEY = 'end';
    public const STARTS_AT_KEY = 'start';
    public const ROLE_ID_KEY = 'roleId';
    public const IS_ACTIVE_KEY = 'isActive';
    public const PERMISSION_ID_KEY = 'permissionId';

    public const ROLE_BINDING_ID_KEY = 'roleBindingId';
    public const PERMISSION_OVERRIDE_ID_KEY = 'permissionOverrideId';

    public const USER_ROLE_CACHE_KEY_PREFIX = 'user:role:cache:';

    /**
     * Returns the user role cache key
     * for a user model
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable $user
     * @return string
     */
    public static function getRoleCacheKeyForUser($user): string {
        return static::USER_ROLE_CACHE_KEY_PREFIX . $user?->id;
    }

    /**
     * Check if the current date range
     * is between the current date and time
     *
     * @param string $startsAt
     * @param string|null $endsAt
     *
     * @return bool
     */
    public static function isGivenDateActive($startsAt, $endsAt): bool {
        $currentTime = Carbon::now();
        return $currentTime->gt($startsAt) && ($endsAt == null || $currentTime->lte($endsAt));
    }

    /**
     * Returns the user Cache data
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable $user
     * @param bool $update
     *
     * @return array
     */
    public static function getUserCache($user, $update = true): array {
        $userCache = [
            'role' => [],
            'permission' => [],
        ];

        $cacheKey = static::getRoleCacheKeyForUser($user);
        if (!Cache::has($cacheKey) && $update == true) {
            static::updateUserCache($user);
        }

        if (Cache::has($cacheKey)) {
            $userCache = Cache::get($cacheKey);
        }

        if (!isset($userCache['role'])) {
            $userCache['role'] = [];
        }

        if (!isset($userCache['permission'])) {
            $userCache['permission'] = [];
        }

        return $userCache;
    }

    /**
     * Returns the Cache data based on the key
     *
     * @param string $cacheKey
     * @return array
     */
    public static function getCacheUsingKey($cacheKey): array {
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        return [];
    }

    /**
     * Sets the user Cache data
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable $user
     * @param array $roleCaches
     * @param array $permissionCaches
     *
     * @return void
     */
    protected static function setUserCache($user, $roleCaches = null, $permissionCaches = null): void {
        $cacheKey = static::getRoleCacheKeyForUser($user);
        $userCache = static::getUserCache($user, update: false);

        if ($roleCaches !== null) {
            $userCache['role'] = $roleCaches;
        }

        if ($permissionCaches !== null) {
            $userCache['permission'] = $permissionCaches;
        }

        Cache::put($cacheKey, $userCache);
    }

    /**
     * Updates the user role binding cache
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable $user
     * @param \App\Models\Role\UserRoleBinding|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $userRoleBindings
     *
     * @return void
     */
    protected static function updateUserRoleBindingCache($user, $userRoleBindings): void {
        if ($userRoleBindings instanceof UserRoleBinding) {
            $userRoleBindings = [$userRoleBindings];
        }

        $userRoleBindingIds = collect($userRoleBindings)
            ->map(fn (UserRoleBinding $binding) => $binding->id)
            ->toArray();

        $userCache = static::getUserCache($user, update: false);
        $roleCaches = collect($userCache['role'])
            ->filter(fn ($role) => !in_array($role[static::ROLE_BINDING_ID_KEY], $userRoleBindingIds))
            ->toArray();

        foreach ($userRoleBindings as $userRoleBinding) {
            $endsAt = $userRoleBinding->ends_at;
            $startsAt = $userRoleBinding->starts_at;

            $bindingProperty = [
                static::STARTS_AT_KEY => $startsAt,
                static::ROLE_ID_KEY => $userRoleBinding->role_id,
                static::ROLE_BINDING_ID_KEY => $userRoleBinding->id,
            ];

            if ($endsAt != null) {
                $bindingProperty[static::ENDS_AT_KEY] = $endsAt;
            }

            array_push($roleCaches, $bindingProperty);
        }

        static::setUserCache($user, roleCaches: $roleCaches);
    }

    /**
     * Updates the user Permission Override cache
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable $user
     * @param \App\Models\Role\UserPermissionOverride|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $userPermissionOverrides
     *
     * @return void
     */
    protected static function updateUserPermissionOverrideCache($user, $userPermissionOverrides): void {
        if ($userPermissionOverrides instanceof UserPermissionOverride) {
            $userPermissionOverrides = [$userPermissionOverrides];
        }

        $userPermissionOverrideIds = collect($userPermissionOverrides)
            ->map(fn (UserPermissionOverride $override) => $override->id)
            ->toArray();

        $userCache = static::getUserCache($user, update: false);
        $permissionCaches = $userCache['permission'];
        $allowedPermissionCaches = collect($permissionCaches['allowed'] ?? [])
            ->filter(fn ($permission) => !in_array($permission[static::PERMISSION_OVERRIDE_ID_KEY], $userPermissionOverrideIds))
            ->toArray();

        $disallowedPermissionCaches = collect($permissionCaches['disallowed'] ?? [])
            ->filter(fn ($permission) => !in_array($permission[static::PERMISSION_OVERRIDE_ID_KEY], $userPermissionOverrideIds))
            ->toArray();

        foreach ($userPermissionOverrides as $userPermissionOverride) {
            $endsAt = $userPermissionOverride->ends_at;
            $startsAt = $userPermissionOverride->starts_at;

            $overrideProperty = [
                static::STARTS_AT_KEY => $startsAt,
                static::PERMISSION_OVERRIDE_ID_KEY => $userPermissionOverride->id,
                static::PERMISSION_ID_KEY => $userPermissionOverride->permission->key,
            ];

            if ($endsAt != null) {
                $overrideProperty[static::ENDS_AT_KEY] = $endsAt;
            }

            if ($userPermissionOverride->allow == true) {
                array_push($allowedPermissionCaches, $overrideProperty);
            } else {
                array_push($disallowedPermissionCaches, $overrideProperty);
            }
        }

        $permissionCaches = [
            'allowed' => $allowedPermissionCaches,
            'disallowed' => $disallowedPermissionCaches,
        ];

        static::setUserCache($user, permissionCaches: $permissionCaches);
    }

    /**
     * Revoke a user cache
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|array $users
     * @return void
     */
    public static function revokeUserCache($users): void {
        if (!is_array($users) && !($users instanceof Collection) && !($users instanceof EloquentCollection)) {
            $users = [$users];
        }

        foreach ($users as $user) {
            $cacheKey = static::getRoleCacheKeyForUser($user);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Revoke a user role binding cache
     *
     * @param \App\Models\Role\UserRoleBinding $userRoleBinding
     * @return void
     */
    public static function revokeUserRoleBindingCache($userRoleBinding): void {
        $user = $userRoleBinding->user;
        $userCache = static::getUserCache($user, update: false);
        $roleCaches = collect($userCache['role'])
            ->filter(fn ($role) => $role[static::ROLE_BINDING_ID_KEY] != $userRoleBinding->id)
            ->toArray();

        static::setUserCache($user, roleCaches: $roleCaches);
    }

    /**
     * Revoke a user permission override cache
     *
     * @param \App\Models\Role\UserPermissionOverride $userPermissionOverride
     * @return void
     */
    public static function revokeUserPermissionOverrideCache($userPermissionOverride): void {
        $user = $userPermissionOverride->user;
        $userCache = static::getUserCache($user, update: false);

        $permissionCaches = $userCache['permission'];
        $allowedPermissionCaches = collect($permissionCaches['allowed'] ?? [])
            ->filter(fn ($permission) => $permission[static::PERMISSION_OVERRIDE_ID_KEY] != $userPermissionOverride->id)
            ->toArray();

        $disallowedPermissionCaches = collect($permissionCaches['disallowed'] ?? [])
            ->filter(fn ($permission) => $permission[static::PERMISSION_OVERRIDE_ID_KEY] != $userPermissionOverride->id)
            ->toArray();

        $permissionCaches = [
            'allowed' => $allowedPermissionCaches,
            'disallowed' => $disallowedPermissionCaches,
        ];

        static::setUserCache($user, permissionCaches: $permissionCaches);
    }

    /**
     * Updates the users role and
     * all permission caches
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|array $users
     * @return void
     */
    public static function updateUserCache($users): void {
        if (!is_array($users) && !($users instanceof Collection) && !($users instanceof EloquentCollection)) {
            $users = [$users];
        }

        foreach ($users as $user) {
            static::setUserCache($user, roleCaches: [], permissionCaches: []);

            $userRoleBindings = UserRoleBinding::query()
                ->where('user_id', $user->id)
                ->whereEndDateIsAfterToday()
                ->get();

            $userPermissionOverrides = UserPermissionOverride::query()
                ->with('permission')
                ->where('user_id', $user->id)
                ->whereEndDateIsAfterToday()
                ->get();

            static::updateUserRoleBindingCache($user, $userRoleBindings);
            static::updateUserPermissionOverrideCache($user, $userPermissionOverrides);
        }
    }

    /**
     * Updates the user role and
     * permission caches from UserRoleBinding
     *
     * @param \App\Models\Role\UserRoleBinding|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $userRoleBindings
     * @return void
     */
    public static function updateUserCacheFromBinding($userRoleBindings): void {
        if (!is_array($userRoleBindings) && !($userRoleBindings instanceof Collection) && !($userRoleBindings instanceof EloquentCollection)) {
            $userRoleBindings = [$userRoleBindings];
        }

        foreach ($userRoleBindings as $userRoleBinding) {
            static::updateUserRoleBindingCache($userRoleBinding->user, $userRoleBinding);
        }
    }

    /**
     * Updates the user role and
     * permission caches from UserPermissionOverride
     *
     * @param \App\Models\Role\UserPermissionOverride|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $userPermissionOverrides
     * @return void
     */
    public static function updateUserCacheFromOverride($userPermissionOverrides): void {
        if (!is_array($userPermissionOverrides) && !($userPermissionOverrides instanceof Collection) && !($userPermissionOverrides instanceof EloquentCollection)) {
            $userPermissionOverrides = [$userPermissionOverrides];
        }

        foreach ($userPermissionOverrides as $userPermissionOverride) {
            static::updateUserPermissionOverrideCache($userPermissionOverride->user, $userPermissionOverride);
        }
    }

    /**
     * Returns the active role caches, optionally
     * narrowed to roles that hold one of the
     * provided permission keys
     *
     * @param array $userCache
     * @param string|array|null $permissionKey
     * @param bool $strictPermissions
     *
     * @return \Illuminate\Support\Collection
     */
    public static function allowedRoleCaches(
        $userCache,
        $permissionKey = null,
        $strictPermissions = false,
    ): Collection {
        return collect($userCache['role'])
            ->map(function ($roleCache) {
                $roleCache[static::IS_ACTIVE_KEY] = static::isGivenDateActive(
                    $roleCache[static::STARTS_AT_KEY],
                    $roleCache[static::ENDS_AT_KEY] ?? null
                );

                return $roleCache;
            })
            ->filter(function ($roleCache) use ($permissionKey, $strictPermissions) {
                if (!$roleCache[static::IS_ACTIVE_KEY]) {
                    return false;
                }

                if ($permissionKey) {
                    $permissionKey = is_array($permissionKey)
                        ? $permissionKey
                        : [$permissionKey];

                    $permissions = PermissionCacheHandler::getRolePermissionKeys($roleCache[static::ROLE_ID_KEY]);
                    $hasOneOfThePermissions = false;
                    foreach ($permissionKey as $permissionId) {
                        $permissionFound = in_array($permissionId, $permissions);
                        if (!$permissionFound && $strictPermissions == true) {
                            return false;
                        }

                        $hasOneOfThePermissions = $hasOneOfThePermissions || $permissionFound;
                    }

                    if (!$hasOneOfThePermissions) {
                        return false;
                    }
                }

                return true;
            });
    }

    /**
     * Returns the active allowed or disallowed
     * permission override caches
     *
     * @param array $permissionCaches
     * @param string|array|null $permissionKey
     * @param bool $allowed
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getResolvedPermissionCaches(
        $permissionCaches,
        $permissionKey = null,
        $allowed = true,
    ): Collection {
        $permissionCaches = $allowed
            ? $permissionCaches['allowed'] ?? []
            : $permissionCaches['disallowed'] ?? [];

        $permissionKey = $permissionKey == null || is_array($permissionKey)
            ? $permissionKey
            : [$permissionKey];

        return collect($permissionCaches)
            ->map(function ($permissionCache) use ($permissionKey) {
                $permissionCache[static::IS_ACTIVE_KEY] = (
                    ($permissionKey == null || in_array($permissionCache[static::PERMISSION_ID_KEY], $permissionKey)) &&
                    static::isGivenDateActive($permissionCache[static::STARTS_AT_KEY], $permissionCache[static::ENDS_AT_KEY] ?? null)
                );

                return $permissionCache;
            })
            ->filter(fn ($permissionCache) => $permissionCache[static::IS_ACTIVE_KEY]);
    }

    /**
     * Returns the permission keys the user holds:
     * role permissions plus allow overrides, minus
     * deny overrides
     *
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user
     *
     * @return array
     */
    public static function getUserPermissionLists($user = null): array {
        $user ??= \Illuminate\Support\Facades\Auth::user();
        $userCache = static::getUserCache($user);

        $allowedRoleCaches = static::allowedRoleCaches($userCache);

        $permissionCaches = $userCache['permission'];
        $allowedPermissions = static::getResolvedPermissionCaches(
            permissionCaches: $permissionCaches,
            allowed: true,
        );

        $disallowedPermissions = static::getResolvedPermissionCaches(
            permissionCaches: $permissionCaches,
            allowed: false,
        );

        $permissionKeys = collect($allowedPermissions)
            ->map(fn ($allowed) => $allowed[static::PERMISSION_ID_KEY])
            ->toArray();

        foreach ($allowedRoleCaches as $allowedRole) {
            $permissionKeys = [
                ...$permissionKeys,
                ...PermissionCacheHandler::getRolePermissionKeys($allowedRole[static::ROLE_ID_KEY]),
            ];
        }

        $disallowedKeys = collect($disallowedPermissions)
            ->map(fn ($disallowed) => $disallowed[static::PERMISSION_ID_KEY])
            ->toArray();

        return array_values(array_unique(array_diff($permissionKeys, $disallowedKeys)));
    }
}
