<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Models\Permission\PermissionGroup;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Translation\Back\English;
use Translation\Message;

class PermissionGroupController extends Controller {
    /**
     * Display all permission
     * Groups
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeePermissionGroup() && !isDropdownEnabled()) {
            return Response::_403();
        }
        $limit = $request->input('limit');
        $query = PermissionGroup::with(['permissionGroup'])->withCount('permissions')->latest('updated_at');

        if ($request->has('search') && !empty($request->search)) {
            $query->jsonbLangValueSearch('name', $request->search);
        }

        $permissionGroups = $query->paginate(static::getPerPage($limit));

        return Response::_200([
            'data' => $permissionGroups->collection(isDropdownEnabled() ? 'dropdown' : null),
            'pagination' => PermissionGroup::extractPagination($permissionGroups),
        ]);
    }

    /**
     * Display a permission Group
     * @param int $permissionGroupId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($permissionGroupId): JsonResponse {
        if (!$this->userCanSeePermissionGroup()) {
            return Response::_403();
        }
        try {
            $permissionGroup = PermissionGroup::query()
                ->with('permissionGroup')
                ->find($permissionGroupId);
            if (!$permissionGroup) {
                return Response::_404(Message::get('permission_group_not_found'));
            }
        } catch (Exception $exception) {
            return Response::_500(Message::get('failed_to_get_permission_group'));
        }
        return Response::_200([
            'data' => $permissionGroup,
        ]);
    }

    /**
     * Create a new Permission group.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse {
        if (!$this->userCanCreatePermissionGroup()) {
            return Response::_403();
        }
        $rules = [
            'permission_group_id' => ['nullable', PermissionGroup::exists()],
            'name' => [
                'required',
                'string',
                'max:' . MAX_NAME_LENGTH,
                PermissionGroup::uniqueJsonBRuleStrict(
                    column: 'name',
                    message: Message::get('duplicate_permission_group_name')
                ),
            ],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('permission_group'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $level = PERMISSION_GROUP_DEFAULT_LEVEL;
        if ($request->filled('permission_group_id')) {
            $parentGroup = PermissionGroup::find(request()->permission_group_id);
            $level = $parentGroup->level + 1;
        }

        $validated = $validator->validated();
        $validated['level'] = $level;
        $validated['user_id'] = Auth::id();
        $validated['code'] = Str::snake($validated['name']);
        $validated['name'] = [English::getKey() => $validated['name']];
        $validated['is_group'] = $request->boolean('is_group', false);

        $permissionGroup = PermissionGroup::create($validated);
        return Response::_201([
            'data' => $permissionGroup,
            'message' => Message::get('permission_group_successfully_created'),
        ]);
    }

    /**
     * Update existing Permission group.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $permissionGroupId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $permissionGroupId): JsonResponse {
        if (!$this->userCanUpdatePermissionGroup()) {
            return Response::_403();
        }

        try {
            $permissionGroup = PermissionGroup::query()
                ->with('permissionGroups')
                ->find($permissionGroupId);
            if (!$permissionGroup) {
                return Response::_404(Message::get('permission_group_not_found'));
            }
        } catch (Exception $exception) {
            return Response::_500(Message::get('failed_to_get_permission_group'));
        }

        $rules = [
            'permission_group_id' => ['nullable', PermissionGroup::exists()],
            'name' => [
                'required',
                'string',
                'max:' . MAX_NAME_LENGTH,
                PermissionGroup::uniqueJsonBRuleStrict(
                    column: 'name',
                    message: Message::get('duplicate_permission_group_name'),
                    ignoreId: $permissionGroupId
                ),
            ],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('permission_group'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($permissionGroupId == request()->permission_group_id) {
            return Response::_422(Message::get('invalid_permission_group_parent'));
        }

        $isParentChildOfCurrentPermission = PermissionGroup::query()
            ->where('permission_group_id', $permissionGroupId)
            ->where('id', request()->permission_group_id)
            ->exists();

        if ($isParentChildOfCurrentPermission) {
            return Response::_422(Message::get('permission_group_can_not_be_added_to_its_own_child'));
        }

        $level = PERMISSION_GROUP_DEFAULT_LEVEL;
        if (request()->filled('permission_group_id')) {
            $parentGroup = PermissionGroup::find(request()->permission_group_id);
            $level = $parentGroup->level + 1;
        }

        $previousParentId = $permissionGroup->permission_group_id;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $permissionGroup->level = $level;
            $permissionGroup->user_id = Auth::id();
            $permissionGroup->name = [English::getKey() => request()->name];
            $permissionGroup->permission_group_id = request()->permission_group_id;
            $permissionGroup->code = Str::snake(request()->name);
            $permissionGroup->is_group = request()->boolean('is_group', $permissionGroup->is_group);
            $permissionGroup->save();

            if ($previousParentId != request()->permission_group_id) {
                $this->updateChildsGroupLevel($permissionGroup);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_update_permission_group'));
        }

        return Response::_200([
            'data' => $permissionGroup,
            'message' => Message::get('permission_group_successfully_updated'),
        ]);
    }

    /**
     * Update the levels of the
     * child that are under the current group
     *
     * @param mixed $permissionGroup
     * @return void
     */
    public function updateChildsGroupLevel($permissionGroup): void {
        foreach ($permissionGroup->permissionGroups as $child) {
            $child->level = $permissionGroup->level - 1;
            $child->save();

            if (count($child->permissionGroups) != 0) {
                $this->updateChildsGroupLevel($child);
            }
        }
    }

    /**
     * Removes permission group from database.
     *
     * @param int $permissionGroupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($permissionGroupId): JsonResponse {
        if (!$this->userCanDeletePermissionGroup()) {
            return Response::_403();
        }

        try {
            $permissionGroup = PermissionGroup::query()
                ->with('permissionGroups')
                ->find($permissionGroupId);
            if (!$permissionGroup) {
                return Response::_404(Message::get('permission_group_not_found'));
            }
        } catch (Exception $exception) {
            return Response::_500(Message::get('failed_to_get_permission_group'));
        }

        $hasChild = PermissionGroup::query()
            ->where('permission_group_id', $permissionGroupId)
            ->exists();

        if ($hasChild) {
            return Response::_422(Message::get('permission_group_has_child'));
        }

        $hasPermissions = $permissionGroup->permissions()->exists();
        if ($hasPermissions) {
            return Response::_422(Message::get('permission_group_has_related_permissions'));
        }

        $permissionGroup->delete();

        return Response::_200([
            'message' => Message::get('permission_group_successfully_deleted'),
        ]);
    }
}
