<?php

namespace Helper\Cache;

use App\Models\Permission\Permission;
use App\Models\Role\Role;
use App\Models\Role\RolePermission;
use Illuminate\Support\Facades\Cache;

class PermissionCacheHandler {

    /**
     * The cache keys that will
     * be used to store the
     * permission cache
     */
    protected const ROLE_CACHE = 'role:cache';
    protected const PERMISSION_CACHE = 'permission:cache';
    protected const PERMISSION_CACHE_FLIPPED = 'permission:cache:flipped';

    protected const ROLE_PERMISSION_ID_CACHE = 'role:permission:id:cache';
    protected const ROLE_PERMISSION_KEY_CACHE = 'role:permission:key:cache';

    /**
     * Returns the cache based
     * on the cache key provided
     *
     * @param string|null $cacheKey
     * @return array
     */
    public static function getCache($cacheKey): array {
        if (!Cache::has($cacheKey)) {
            static::updateCache();
        }

        return Cache::get($cacheKey, []);
    }

    /**
     * Returns the role ids cache
     *
     * @return array
     */
    public static function getRoleIdsCache(): array {
        return static::getCache(static::ROLE_CACHE);
    }

    /**
     * Returns the role permission ids
     *
     * @param int $roleId
     * @return array
     */
    public static function getRolePermissionIds($roleId): array {
        $cache = static::getCache(static::ROLE_PERMISSION_ID_CACHE);
        return $cache[$roleId] ?? [];
    }

    /**
     * Returns the role permission keys
     *
     * @param int $roleId
     * @return array
     */
    public static function getRolePermissionKeys($roleId): array {
        $cache = static::getCache(static::ROLE_PERMISSION_KEY_CACHE);
        return $cache[$roleId] ?? [];
    }

    /**
     * Updates the role caches
     * caches
     *
     * @return void
     */
    public static function updateRoleCache(): void {
        $roles = Role::query()
            ->pluck('id')
            ->toArray();

        Cache::put(static::ROLE_CACHE, $roles);
    }

    /**
     * Updates the permission
     * caches
     *
     * @return void
     */
    public static function updateCache(): void {
        $permissionMap = [];
        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            $permissionMap[$permission->key] = $permission->id;
        }

        $permissionMapFlipped = array_flip($permissionMap);

        $rolePermissionIdMap = [];
        $rolePermissionKeyMap = [];
        $rolePermissions = RolePermission::all();
        foreach ($rolePermissions as $rolePermission) {
            $roleId = $rolePermission->role_id;
            $permissionId = $rolePermission->permission_id;

            $rolePermissionIdMap[$roleId] ??= [];
            $rolePermissionKeyMap[$roleId] ??= [];

            array_push($rolePermissionIdMap[$roleId], $permissionId);
            array_push($rolePermissionKeyMap[$roleId], $permissionMapFlipped[$permissionId]);
        }

        Cache::put(static::PERMISSION_CACHE, $permissionMap);
        Cache::put(static::PERMISSION_CACHE_FLIPPED, $permissionMapFlipped);

        Cache::put(static::ROLE_PERMISSION_ID_CACHE, $rolePermissionIdMap);
        Cache::put(static::ROLE_PERMISSION_KEY_CACHE, $rolePermissionKeyMap);
    }

    /**
     * Returns the permission key
     * lists from a given permission ids
     *
     * @param array $permissionIds
     * @return array
     */
    public static function getPermissionKeys($permissionIds): array {
        $flippedMap = static::getCache(static::PERMISSION_CACHE_FLIPPED);
        $filteredPermissions = array_filter(
            $flippedMap,
            fn ($permissionKey) => in_array($permissionKey, $permissionIds),
            ARRAY_FILTER_USE_KEY
        );

        return array_values($filteredPermissions);
    }
}