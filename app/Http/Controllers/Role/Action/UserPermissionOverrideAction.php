<?php

namespace App\Http\Controllers\Role\Action;

use App\Http\Requests\Role\UserPermissionOverrideRequest;
use App\Models\Permission\Permission;
use App\Models\Role\UserPermissionOverride;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use Carbon\Carbon;
use Constants\AppConstant;
use Exception;
use Helper\Cache\RoleCacheHandler;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Translation\Message;

trait UserPermissionOverrideAction {
    /**
     * Assign permission to a user
     *
     * @param \App\Http\Requests\Role\UserPermissionOverrideRequest $request
     * @param int $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissionToUser(UserPermissionOverrideRequest $request, $userId): JsonResponse {
        $permissions = Permission::query()
            ->whereIn('id', request()->permission_ids)
            ->where('state', STATE_ACTIVE)
            ->get();

        if (count($permissions) == 0) {
            return Response::_404(Message::get('permissions_could_not_be_found'));
        }

        $user = User::query()
            ->whereNot('id', Auth::id())
            ->find($userId);

        if (!$user) {
            return Response::_404(Message::get('user_could_not_be_found'));
        }

        if ($user->state != USER_STATE_ACTIVE) {
            return Response::_422(Message::get('cannot_assign_permission_to_inactive_user'));
        }

        $validated = $request->validated();

        foreach ($permissions as $permission) {
            if (!$permission->unique_per_user) {
                continue;
            }

            $hasUserPermission = UserPermissionOverride::query();
            if ($request->has('ends_at')) {
                $hasUserPermission
                    ->where(function ($query) use ($validated) {
                        $query
                            ->orWhereNull('ends_at')
                            ->orWhereBetween('ends_at', [$validated['starts_at'], $validated['ends_at']])
                            ->orWhereBetween('starts_at', [$validated['starts_at'], $validated['ends_at']]);
                    });
            } else {
                $hasUserPermission->withInActiveDateRange();
            }

            $hasUserPermission = $hasUserPermission
                ->where('permission_id', $permission->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($hasUserPermission) {
                $bindings = [
                    'permission' => $permission->name__localized,
                    'user' => $user->full_name__localized,
                ];

                return Response::_422(Message::get('you_can_not_assign_this_permission_multiple_times', $bindings));
            }
        }

        $allOverrides = [];
        $bindings = ['user' => $user->full_name__localized];
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($permissions as $permission) {
                $userPermissionOverride = UserPermissionOverride::query()
                    ->where('permission_id', $permission->id)
                    ->where('user_id', $userId)
                    ->first();

                if (!$userPermissionOverride) {
                    $userPermissionOverride = new UserPermissionOverride();
                    $userPermissionOverride->user_id = $userId;
                    $userPermissionOverride->permission_id = $permission->id;
                }

                $bindings = [
                    'permission' => $permission->name__localized,
                    'user' => $user->full_name__localized,
                ];

                $userPermissionOverride->assigned_by = Auth::id();
                $userPermissionOverride->allow = $validated['allow'];
                $userPermissionOverride->ends_at = $validated['ends_at'] ?? null;
                $userPermissionOverride->starts_at = Carbon::parse($validated['starts_at']);
                $userPermissionOverride->save();

                array_push($allOverrides, $userPermissionOverride);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $message = $exception->getMessage();
            $errorKey = 'unable_to_assign_permission_to_user';
            if (str_contains($message, USER_PERMISSION_UNIQUE_KEY)) {
                $errorKey = 'permission_is_already_assigned_to_user';
            }

            return Response::_422(Message::get($errorKey, $bindings));
        }

        // ToDo: Needs to be in a scheduler
        RoleCacheHandler::updateUserCacheFromOverride($allOverrides);

        $permissionNames = collect($permissions)
            ->map(fn ($permission) => $permission->name__localized)
            ->toArray();

        $bindings = [
            'user' => $user->full_name__localized,
            'permissions' => implode(', ', $permissionNames),
        ];

        return Response::_200([
            'message' => Message::get('permissions_assigned_to_user', $bindings),
        ]);
    }

    /**
     * Get user permissions overrides by user id
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissionOverrides($userId): JsonResponse {
        if (!$this->userCanAssignPermissionToUser()) {
            return Response::_403();
        }

        // ToDo: Needs to add Role check
        if (!$userId || !User::exists($userId)) {
            return Response::_404(Message::get('user_not_found'));
        }

        $overrides = UserPermissionOverride::query()
            ->with('permission', 'assignedBy')
            ->where('user_id', $userId)
            ->withInActiveDateRange()
            ->orderBy('created_at', 'desc')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $overrides->collection(),
            'pagination' => UserPermissionOverride::extractPagination($overrides),
        ]);
    }

    /**
     * The user's role-inherited permissions grouped by role
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoleInheritedPermissions($userId): JsonResponse {
        if (!$this->userCanAssignPermissionToUser()) {
            return Response::_403();
        }

        if (!$userId || !User::exists($userId)) {
            return Response::_404(Message::get('user_not_found'));
        }

        $bindings = UserRoleBinding::query()
            ->with(['role.rolePermissions.permission'])
            ->where('user_id', $userId)
            ->withInActiveDateRange()
            ->get();

        // Active deny-overrides for this user, keyed by permission id.
        $denyOverrides = UserPermissionOverride::query()
            ->where('user_id', $userId)
            ->where('allow', false)
            ->withInActiveDateRange()
            ->get()
            ->keyBy('permission_id');

        $roles = $bindings
            ->map(fn ($binding) => $binding->resource($binding->inheritedPermissionsRoleFields($denyOverrides)))
            ->values();

        return Response::_200(['data' => $roles]);
    }

    /**
     * Revoke a role-inherited permission by writing an explicit deny-override
     * (allow = false) for the user.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeInheritedPermission(Request $request, $userId): JsonResponse {
        if (!$this->userCanAssignPermissionToUser()) {
            return Response::_403();
        }

        if (!$userId || !User::exists($userId)) {
            return Response::_404(Message::get('user_not_found'));
        }

        $permissionId = $request->input('permission_id');
        if (!$permissionId) {
            return Response::_422(Message::get('permission_could_not_be_found'));
        }

        $endsAt = $request->input('ends_at');

        $override = UserPermissionOverride::query()
            ->where('user_id', $userId)
            ->where('permission_id', $permissionId)
            ->first();

        if (!$override) {
            $override = new UserPermissionOverride();
            $override->user_id = $userId;
            $override->permission_id = $permissionId;
        }

        $override->allow = false;
        $override->assigned_by = Auth::id();
        $override->starts_at = Carbon::now();
        $override->ends_at = $endsAt
            ? Carbon::parse($endsAt)
            : null;
        $override->save();

        RoleCacheHandler::updateUserCacheFromOverride([$override]);

        return Response::_200([
            'data' => $override->resource('denyOverrideFields'),
            'message' => Message::get('permissions_assigned_to_user'),
        ]);
    }

    /**
     * Restore a previously revoked role-inherited permission by dropping the
     * deny-override(s), so the role's grant applies again.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreInheritedPermission(Request $request, $userId): JsonResponse {
        if (!$this->userCanAssignPermissionToUser()) {
            return Response::_403();
        }

        if (!$userId || !User::exists($userId)) {
            return Response::_404(Message::get('user_not_found'));
        }

        $permissionId = $request->input('permission_id');
        if (!$permissionId) {
            return Response::_422(Message::get('permission_could_not_be_found'));
        }

        $overrides = UserPermissionOverride::query()
            ->where('user_id', $userId)
            ->where('permission_id', $permissionId)
            ->where('allow', false)
            ->get();

        $deleted = [];
        foreach ($overrides as $override) {
            $deleted[] = $override->resource('denyOverrideFields');
            $override->delete();
        }

        $user = User::find($userId);
        if ($user) {
            RoleCacheHandler::updateUserCache([$user]);
        }

        return Response::_200([
            'data' => $deleted,
            'message' => Message::get('deleted_successfully'),
        ]);
    }

    /**
     * Permanently delete a single direct permission-override period.
     *
     * @param int $userId
     * @param int $overrideId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUserPermissionOverride($userId, $overrideId): JsonResponse {
        if (!$this->userCanAssignPermissionToUser()) {
            return Response::_403();
        }

        $override = UserPermissionOverride::query()
            ->where('user_id', $userId)
            ->find($overrideId);

        if (!$override) {
            return Response::_404(Message::get('permission_could_not_be_found'));
        }

        $data = $override->resource('denyOverrideFields');
        $override->delete();

        RoleCacheHandler::revokeUserPermissionOverrideCache($override);

        return Response::_200([
            'data' => $data,
            'message' => Message::get('deleted_successfully'),
        ]);
    }
}
