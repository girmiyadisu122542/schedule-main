<?php

namespace Helper\Permission;

use Exception;
use Helper\Cache\RoleCacheHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait PermissionAction {

    /**
     * dynamically call a permission
     * for example instead of calling
     *
     * Before: $this->userCan(PERMISSION_CREATE_ROLE)
     * After:  $this->userCanCreateRole()
     *
     * @param string $methodName
     * @param mixed $arguments
     *
     * @return mixed
     */
    public function __call($methodName, $arguments): mixed {
        $userCan = 'userCan';
        if (str_starts_with($methodName, $userCan)) {
            $suffix = str_replace($userCan, '', $methodName);
            $suffix = Str::upper(Str::snake($suffix));

            $permissionName = "PERMISSION_$suffix"; // eg. PERMISSION_CREATE_ROLE
            $permissionKey = constant($permissionName); // eg. role:create
            return $this->userCan($permissionKey, ...$arguments);
        }

        if (get_parent_class() == false) {
            $className = static::class;
            throw new Exception("Call to undefined method $className::$methodName()");
        }

        return parent::__call($methodName, $arguments);
    }

    /**
     * Check if the user has been assigned
     * the specified permission.
     *
     * Flat RBAC resolution order:
     *  1. an active deny override blocks the permission,
     *  2. an active allow override grants it,
     *  3. otherwise any active role binding whose role holds the key grants it.
     *
     * @param array|string $permissionKey
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user
     * @param bool $strictPermissions
     *
     * @return bool
     */
    public function userCan(
        $permissionKey,
        $user = null,
        $strictPermissions = false,
    ): bool {
        $user ??= Auth::user();
        $userCache = RoleCacheHandler::getUserCache($user);
        if (!is_array($permissionKey)) {
            $permissionKey = [$permissionKey];
        }

        $permissionKey = array_unique($permissionKey);

        $permissionCaches = $userCache['permission'];
        $disallowed = RoleCacheHandler::getResolvedPermissionCaches(
            permissionCaches: $permissionCaches,
            permissionKey: $permissionKey,
            allowed: false,
        );

        $disallowed = $disallowed
            ->map(fn ($disal) => $disal[RoleCacheHandler::PERMISSION_ID_KEY])
            ->toArray();

        $disallowedCount = count(array_unique($disallowed));
        $permissionsCount = count($permissionKey);
        if ($disallowedCount > 0) {
            if (
                ($disallowedCount == $permissionsCount) ||
                ($disallowedCount != $permissionsCount && $strictPermissions == true)
            ) {
                return false;
            }
        }

        $allowed = RoleCacheHandler::getResolvedPermissionCaches(
            permissionCaches: $permissionCaches,
            permissionKey: $permissionKey,
            allowed: true,
        )
            ->toArray();

        $hasRawPermissionAccess = false;
        if (count($allowed) > 0) {
            $hasRawPermissionAccess = true;
        }

        $roleCaches = RoleCacheHandler::allowedRoleCaches(
            userCache: $userCache,
            permissionKey: $permissionKey,
            strictPermissions: $strictPermissions,
        );

        $roleCaches = $roleCaches->toArray();
        if (count($roleCaches) > 0) {
            $hasRawPermissionAccess = true;
        }

        if (!$hasRawPermissionAccess) {
            return false;
        }

        return true;
    }
}
