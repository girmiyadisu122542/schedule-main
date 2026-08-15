<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\PermissionBulkOperationRequest;
use App\Http\Requests\Permission\PermissionStoreRequest;
use App\Http\Requests\Permission\PermissionUpdateRequest;
use App\Models\Permission\Permission;
use App\Models\Role\RolePermission;
use App\Models\Role\UserPermissionOverride;
use Exception;
use Helper\Cache\PermissionCacheHandler;
use Helper\Response\Response;
use Helper\Type\State\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Translation\Back\English;
use Translation\Message;

class PermissionController extends Controller {
    /**
     * Display all permission
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeePermission() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->filled('search')
            ? trim($request->input('search'))
            : null;
        $groupIds = $this->normalizeIntegerArray($request->input('permission_group_id'));
        $uniquePerUser = !is_null($request->input('unique_per_user'))
            ? $request->boolean('unique_per_user')
            : null;

        // Grant flows scope the list to permissions the CURRENT (authenticated)
        // user actually holds. The cache handler resolves the effective role + override permission keys.
        $authorizedOnly = $request->boolean('authorized_only');
        $authorizedKeys = $authorizedOnly
            ? (Auth::user()?->getAllPermissions() ?? [])
            : [];

        $permissions = Permission::query()
            ->when($authorizedOnly, fn ($query) => $query->whereIn('key', $authorizedKeys))
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->orJsonbLangValueSearch('name', $search)
                            ->orWhere('key', 'like', '%' . $search . '%');
                    });
            })
            ->when(!is_null($uniquePerUser), fn ($query) => $query->where('unique_per_user', $uniquePerUser))
            ->when($groupIds, fn ($query) => $query->whereIn('permission_group_id', $groupIds))
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => Permission::collection($permissions),
            'pagination' => Permission::extractPagination($permissions),
        ]);
    }

    /**
     * Normalize input to an array of integers.
     *
     * @param mixed $value
     * @return array
     */
    private function normalizeIntegerArray(mixed $value): array {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value)
            ? $value
            : explode(',', $value);

        return array_values(array_map(
            'intval',
            array_filter($values, fn ($item) => $item !== null && $item !== '')
        ));
    }

    /**
     * Create a new Permission.
     *
     * @param \App\Http\Requests\Permission\PermissionStoreRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PermissionStoreRequest $request): JsonResponse {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();
        $validated['state'] = STATE_INACTIVE;
        $validated['name'] = [English::getKey() => $validated['name']];

        try {
            $permission = Permission::create($validated);
        } catch (Exception $exception) {
            return Response::_422(Message::get('unable_to_create_permission'));
        }

        $bindings = ['name' => $permission->name_localized];

        return Response::_201([
            'data' => $permission->resource(),
            'message' => Message::get('permission_successfully_created', $bindings),
        ]);
    }

    /**
     * Update existing Permission.
     *
     * @param \App\Http\Requests\Permission\PermissionUpdateRequest $request
     * @param int $permissionId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PermissionUpdateRequest $request, $permissionId): JsonResponse {
        $validated = $request->validated();
        $permission = Permission::findOrFail($permissionId);
        $permission->user_id = Auth::id();

        if (isset($validated['name'])) {
            $validated['name'] = [English::getKey() => $validated['name']];
        }

        foreach ($validated as $key => $value) {
            $permission->{$key} = $value;
        }
        unset($request->state);
        unset($request->key);

        try {
            $permission->save();
        } catch (Exception $exception) {
            return Response::_422(Message::get('unable_to_update_permission'));
        }

        $permission->load('permissionGroup:id,name');
        $bindings = ['name' => $permission->name_localized];

        return Response::_200([
            'data' => $permission->resource(),
            'message' => Message::get('permission_successfully_updated', $bindings),
        ]);
    }

    /**
     * Removes permission from database.
     *
     * @param int $permissionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($permissionId): JsonResponse {
        if (!$this->userCanDeletePermission()) {
            return Response::_401();
        }

        $permission = Permission::findOrFail($permissionId);
        $hasRolePermission = RolePermission::query()
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasRolePermission) {
            return Response::_422(Message::get('permission_has_been_assigned_to_a_role'));
        }

        $hasPermissionOverrides = UserPermissionOverride::query()
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasPermissionOverrides) {
            return Response::_422(Message::get('permission_has_been_assigned_to_a_user'));
        }

        $permission->delete();

        $bindings = ['name' => $permission->name_localized];
        return Response::_200([
            'message' => Message::get('permission_successfully_deleted', $bindings),
        ]);
    }

    /**
     * Change the state of the
     * permission between Active and Inactive
     *
     * @param \Illuminate\Http\Request $request
     * @param int $permissionId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState(Request $request, $permissionId): JsonResponse {
        if (!$this->userCanChangePermissionStatus()) {
            return Response::_401();
        }

        $permission = Permission::findOrFail($permissionId);
        $rules = [
            'state' => ['required', State::ruleIn()],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('permission'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($permission->state == request()->state) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $permission->state = request()->state;
        $permission->save();

        $bindings = ['name' => $permission->name_localized];
        $message = request()->state == STATE_ACTIVE
            ? 'permission_successfully_activated'
            : 'permission_successfully_deactivated';

        return Response::_200([
            'data' => $permission->resource(),
            'message' => Message::get($message, $bindings),
        ]);
    }

    /**
     * Activate, deactivate or delete a batch of permissions at once.
     * Delete skips permissions still assigned to a role or user.
     *
     * @param \App\Http\Requests\Permission\PermissionBulkOperationRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleBulkAction(PermissionBulkOperationRequest $request): JsonResponse {
        $ids = $request->permission_ids;
        $actionType = $request->action_type;
        $message = 'action_not_found';

        try {
            if ($actionType == ACTION_ACTIVATE || $actionType == ACTION_DEACTIVATE) {
                if (!$this->userCanChangePermissionStatus()) {
                    return Response::_401();
                }

                $state = $actionType == ACTION_ACTIVATE
                    ? STATE_ACTIVE
                    : STATE_INACTIVE;

                Permission::query()
                    ->whereIn('id', $ids)
                    ->update(['state' => $state]);

                $message = $actionType == ACTION_ACTIVATE
                    ? 'permissions_activated_successfully'
                    : 'permissions_deactivated_successfully';
            } else if ($actionType == ACTION_DELETE) {
                if (!$this->userCanDeletePermission()) {
                    return Response::_401();
                }

                $rolePermissionIds = RolePermission::query()
                    ->whereIn('permission_id', $ids)
                    ->pluck('permission_id');

                $overridePermissionIds = UserPermissionOverride::query()
                    ->whereIn('permission_id', $ids)
                    ->pluck('permission_id');

                $inUse = $rolePermissionIds
                    ->merge($overridePermissionIds)
                    ->unique();

                $deletable = collect($ids)
                    ->diff($inUse)
                    ->values();
                Permission::query()
                    ->whereIn('id', $deletable)
                    ->delete();

                $message = $inUse->isEmpty()
                    ? 'permissions_deleted_successfully'
                    : 'some_permissions_deleted_others_in_use';
            }

            PermissionCacheHandler::updateCache();
        } catch (Exception $exception) {
            return Response::_500(Message::get('bulk_action_failed'));
        }

        return Response::_200([
            'message' => Message::get($message),
        ]);
    }
}
