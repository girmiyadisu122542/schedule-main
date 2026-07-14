<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Role\Action\RolePermissionAction;
use App\Http\Controllers\Role\Action\UserPermissionOverrideAction;
use App\Http\Controllers\Role\Action\UserRoleBindingAction;
use App\Http\Requests\Role\RoleBulkOperationRequest;
use App\Http\Requests\Role\RoleStoreRequest;
use App\Http\Requests\Role\RoleUpdateRequest;
use App\Models\Role\Role;
use App\Models\Role\RolePermission;
use App\Models\Role\UserRoleBinding;
use Exception;
use Helper\Cache\PermissionCacheHandler;
use Helper\Response\Response;
use Helper\Type\State\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class RoleController extends Controller {
    use RolePermissionAction,
        UserRoleBindingAction,
        UserPermissionOverrideAction;

    /**
     * Display all roles
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeRole() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $state = $request->input('state');
        $search = $request->input('search');
        $userRoles = $this->normalizeIntegerArray($request->input('user_roles'));

        $isSystem = !is_null($request->input('is_system'))
            ? $request->boolean('is_system')
            : null;

        $uniquePerUser = !is_null($request->input('unique_per_user'))
            ? $request->boolean('unique_per_user')
            : null;

        $isDropdown = isDropdownEnabled();
        $permissions = $isDropdown
            ? (getPermissionsFromRequest() ?: PERMISSION_SEE_ROLE)
            : PERMISSION_SEE_ROLE;

        $roles = Role::query()
            ->applyRoleBasedQuery(permissionKey: $permissions)
            ->when(!$isDropdown, fn ($query) => $query
                ->withCount([
                    'rolePermissions AS permissions_count',
                    'userRoleBindings AS users_count' => fn ($query) => $query->selectRaw('COUNT(distinct user_id)'),
                ]))
            ->when(!is_null($uniquePerUser), fn ($query) => $query->where('unique_per_user', $uniquePerUser))
            ->when($state !== null && $state !== '', fn ($query) => $query->where('state', (int) $state))
            ->when(!is_null($isSystem), fn ($query) => $query->where('is_system', $isSystem))
            ->when($search, fn ($query) => $query->jsonbLangValueSearch('name', $search))
            ->when($userRoles, fn ($query) => $query->whereNotIn('id', $userRoles))
            ->latest()
            ->paginate(static::getPerPage());

        $data = $isDropdown
            ? $roles->collection('idAndNameFields')
            : $roles->collection('listFields');

        return Response::_200([
            'data' => $data,
            'pagination' => Role::extractPagination($roles),
        ]);
    }

    /**
     * Aggregate role metrics for the management dashboard cards
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(): JsonResponse {
        if (!$this->userCanSeeRole()) {
            return Response::_403();
        }

        $query = fn () => Role::applyRoleBasedQuery(permissionKey: PERMISSION_SEE_ROLE);
        $stats = [
            'total' => $query()->count(),
            'active' => $query()->where('state', STATE_ACTIVE)->count(),
            'inactive' => $query()->where('state', STATE_INACTIVE)->count(),
            'system' => $query()->where('is_system', true)->count(),
            'custom' => $query()->where('is_system', false)->count(),
            'unassigned' => $query()->whereDoesntHave('userRoleBindings')->count(),
            'assigned_users' => UserRoleBinding::query()
                ->whereIn('role_id', $query()->select('id')->pluck('id'))
                ->distinct('user_id')
                ->count('user_id'),
        ];

        return Response::_200([
            'data' => $stats,
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
     * Create a new role.
     *
     * @param \App\Http\Requests\Role\RoleStoreRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(RoleStoreRequest $request): JsonResponse {
        $validated = $request->validated();
        $language = getCurrentLanguage($request);

        $validated['user_id'] = Auth::id();
        $validated['state'] = STATE_ACTIVE;
        $validated['name'] = [$language => $validated['name']];
        $validated['description'] = [$language => $validated['description']];

        try {
            $role = Role::create($validated);
        } catch (Exception $exception) {
            return Response::_422(Message::get('unable_to_create_role'));
        }

        $bindings = ['name' => $role->name__localized];

        return Response::_201([
            'data' => $role->resource(),
            'message' => Message::get('role_successfully_created', $bindings),
        ]);
    }

    /**
     * Update existing role.
     *
     * @param \App\Http\Requests\Role\RoleUpdateRequest $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(RoleUpdateRequest $request, $roleId): JsonResponse {
        if (!$this->userCanUpdateRole()) {
            return Response::_403();
        }

        $validated = $request->validated();
        $language = getCurrentLanguage($request);

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_UPDATE_ROLE)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('role_not_found'));
        }

        if (isset($validated['name'])) {
            $validated['name'] = [$language => $validated['name']];
        }

        if (isset($validated['description'])) {
            $validated['description'] = [$language => $validated['description']];
        }

        foreach ($validated as $key => $value) {
            $role->{$key} = $value;
        }

        try {
            $role->save();
        } catch (Exception $exception) {
            return Response::_422(Message::get('unable_to_update_role'));
        }

        $bindings = ['name' => $role->name__localized];

        return Response::_200([
            'data' => $role->resource(),
            'message' => Message::get('role_successfully_updated', $bindings),
        ]);
    }

    /**
     * Removes role from database.
     *
     * @param int $roleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($roleId): JsonResponse {
        if (!$this->userCanDeleteRole()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_DELETE_ROLE)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('role_not_found'));
        }

        $permissionsCount = RolePermission::query()
            ->where('role_id', $roleId)
            ->count();

        $bindingsCount = UserRoleBinding::query()
            ->where('role_id', $roleId)
            ->count();

        if ($permissionsCount > 0 || $bindingsCount > 0) {
            $errors = [];

            if ($permissionsCount > 0) {
                $errors['permissions'] = Message::get('role_has_permissions_with_count', ['count' => $permissionsCount]);
            }

            if ($bindingsCount > 0) {
                $errors['bindings'] = Message::get('role_has_bindings_with_count', ['count' => $bindingsCount]);
            }

            return Response::_422(
                Message::get('role_has_dependencies'),
                $errors
            );
        }

        $role->delete();

        $bindings = ['name' => $role->name__localized];
        return Response::_200([
            'message' => Message::get('role_successfully_deleted', $bindings),
        ]);
    }

    /**
     * Change the role type between
     * True and False
     *
     * @param \Illuminate\Http\Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeRoleType(Request $request, $roleId): JsonResponse {
        if (!$this->userCanChangeRoleType()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_CHANGE_ROLE_TYPE)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('role_not_found'));
        }

        $rules = [
            'is_system' => ['required', 'boolean'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($role->is_system == request()->boolean('is_system')) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $role->is_system = request()->boolean('is_system');
        $role->save();

        $bindings = ['name' => $role->name__localized];
        $message = request()->boolean('is_system')
            ? 'role_successfully_changed_to_system'
            : 'role_successfully_changed_to_non_system';

        return Response::_200([
            'data' => $role->resource(),
            'message' => Message::get($message, $bindings),
        ]);
    }

    /**
     * Change the state of the
     * role between Active and Inactive
     *
     * @param \Illuminate\Http\Request $request
     * @param int $roleId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState(Request $request, $roleId): JsonResponse {
        if (!$this->userCanChangeRoleState()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_CHANGE_ROLE_STATUS)
            ->where('id', $roleId)
            ->first();
        if (!$role) {
            return Response::_404(Message::get('role_not_found'));
        }

        $rules = [
            'state' => ['required', State::ruleIn()],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('roles'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($role->state == request()->state) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $role->state = request()->state;
        $role->save();

        $bindings = ['name' => $role->name__localized];
        $message = request()->state == STATE_ACTIVE
            ? 'role_successfully_activated'
            : 'role_successfully_deactivated';

        return Response::_200([
            'data' => $role->resource(),
            'message' => Message::get($message, $bindings),
        ]);
    }

    /**
     * Activate or deactivate a batch of roles at once.
     *
     * @param \App\Http\Requests\Role\RoleBulkOperationRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleBulkAction(RoleBulkOperationRequest $request): JsonResponse {
        if (!$this->userCanChangeRoleState()) {
            return Response::_401();
        }

        $roleIds = $request->role_ids;
        $actionType = $request->action_type;
        $message = 'action_not_found';

        try {
            if ($actionType == ACTION_ACTIVATE) {
                $message = 'roles_activated_successfully';
                Role::query()
                    ->applyRoleBasedQuery(permissionKey: PERMISSION_CHANGE_ROLE_STATE)
                    ->whereIn('id', $roleIds)
                    ->update(['state' => STATE_ACTIVE]);
            } else if ($actionType == ACTION_DEACTIVATE) {
                $message = 'roles_deactivated_successfully';
                Role::query()
                    ->applyRoleBasedQuery(permissionKey: PERMISSION_CHANGE_ROLE_STATE)
                    ->whereIn('id', $roleIds)
                    ->update(['state' => STATE_INACTIVE]);
            }

            PermissionCacheHandler::updateRoleCache();
        } catch (Exception $exception) {
            return Response::_500(Message::get('bulk_action_failed'));
        }

        return Response::_200([
            'message' => Message::get($message),
        ]);
    }
}
