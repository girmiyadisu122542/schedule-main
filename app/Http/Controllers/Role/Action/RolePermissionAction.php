<?php

namespace App\Http\Controllers\Role\Action;

use App\Models\Permission\Permission;
use App\Models\Permission\PermissionGroup;
use App\Models\Role\Role;
use App\Models\Role\RolePermission;
use Helper\Cache\PermissionCacheHandler;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

trait RolePermissionAction {
    /**
     * Lists all permissions
     * of a specific role
     *
     * @param \Illuminate\Http\Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function showPermissions(Request $request, $roleId): JsonResponse {
        if (!$this->userCanSeeRolePermission()) {
            return Response::_403();
        }

        $rolePermissions = RolePermission::query()
            ->where('role_id', $roleId)
            ->get();

        return Response::_200([
            'data' => RolePermission::collection($rolePermissions),
        ]);
    }

    /**
     * Get permission groups with their permissions
     *
     * @param int $roleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRolePermissionGroups($roleId): JsonResponse {
        if (!$this->userCanSeeRolePermission()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_ROLE_PERMISSION)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('can_not_find_role'));
        }

        $grantedIds = RolePermission::query()
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->toArray();

        // Only surface permissions the authenticated user actually holds
        $authorizedKeys = Auth::user()?->getAllPermissions() ?? [];

        $groups = PermissionGroup::query()
            ->with([
                'permissions' => function ($query) use ($authorizedKeys) {
                    $query
                        ->select(['id', 'name', 'key', 'permission_group_id'])
                        ->whereIn('key', $authorizedKeys);
                },
            ])
            ->get();

        $fields = (new PermissionGroup())->rolePermissionFields($grantedIds);
        $data = $groups->collection($fields);

        return Response::_200(['data' => $data]);
    }

    /**
     * Assign permissions to a role
     *
     * @param \Illuminate\Http\Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPermissions(Request $request, $roleId): JsonResponse {
        if (!$this->userCanAddRolePermission()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_ADD_ROLE_PERMISSION)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('can_not_find_role'));
        }

        $rules = [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', 'distinct', Permission::exists()],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('roles'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $insertOrUpdateMap = [];
        foreach (request()->permissions as $permission) {
            array_push($insertOrUpdateMap, [
                'role_id' => $role->id,
                'user_id' => Auth::id(),
                'state' => STATE_ACTIVE,
                'permission_id' => $permission,
            ]);
        }

        RolePermission::upsert($insertOrUpdateMap, ROLE_PERMISSION_UNIQUE_COLUMN);
        PermissionCacheHandler::updateCache();

        $rolePermissions = RolePermission::query()
            ->whereIn('permission_id', request()->permissions)
            ->where('role_id', $role->id)
            ->get();

        $bindings = ['name' => $role->name__localized];
        return Response::_201([
            'data' => RolePermission::collection($rolePermissions),
            'message' => Message::get('permissions_successfully_added_to_role', $bindings),
        ]);
    }

    /**
     * Remove existing permissions from a role
     *
     * @param \Illuminate\Http\Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removePermissions(Request $request, $roleId): JsonResponse {
        if (!$this->userCanRemoveRolePermission()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_REMOVE_ROLE_PERMISSION)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('can_not_find_role'));
        }

        $rules = [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', 'distinct', Permission::exists()],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('roles'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        RolePermission::query()
            ->whereIn('permission_id', request()->permissions)
            ->where('role_id', $role->id)
            ->delete();

        // Required because the delete above runs on the query builder, which
        // does NOT fire RolePermission's `deleted` hook — so nothing else
        // refreshes the permission cache. Without it the row is gone while
        // every holder of the role keeps the permission indefinitely: a revoked
        // permission that is still enforced as granted. `addPermissions` and
        // `setPermissions` already call this for the same reason.
        PermissionCacheHandler::updateCache();

        $bindings = ['name' => $role->name__localized];
        return Response::_200([
            'message' => Message::get('permissions_successfully_removed_from_role', $bindings),
        ]);
    }

    /**
     * Create or update role permissions
     *
     * @param \Illuminate\Http\Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setPermissions(Request $request, $roleId): JsonResponse {
        if (!$this->userCanAddRolePermission() && !$this->userCanRemoveRolePermission()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_ADD_ROLE_PERMISSION)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('can_not_find_role'));
        }

        $permissionsInput = $request->input('permissions', []);
        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'distinct', Permission::exists()],
        ];

        $validator = Validator::make(['permissions' => $permissionsInput], $rules, Message::get('roles'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $permissions = array_map('intval', $permissionsInput);

        RolePermission::query()
            ->whereNotIn('permission_id', $permissions)
            ->where('role_id', $role->id)
            ->delete();

        $currentPermissions = RolePermission::query()
            ->where('role_id', $role->id)
            ->pluck('permission_id')
            ->toArray();

        $permissionsToAdd = array_diff($permissions, $currentPermissions);

        if (!empty($permissionsToAdd)) {
            $insert = array_map(fn ($perm) => [
                'role_id' => $role->id,
                'user_id' => Auth::id(),
                'state' => STATE_ACTIVE,
                'permission_id' => $perm,
            ], $permissionsToAdd);

            RolePermission::upsert($insert, ROLE_PERMISSION_UNIQUE_COLUMN);
        }

        PermissionCacheHandler::updateCache();
        $rolePermissions = RolePermission::where('role_id', $role->id)->get();

        return Response::_200([
            'data' => RolePermission::collection($rolePermissions),
            'message' => Message::get('role_permissions_updated', ['name' => $role->name__localized]),
        ]);
    }
}
