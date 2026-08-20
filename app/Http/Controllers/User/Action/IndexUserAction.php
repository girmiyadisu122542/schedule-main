<?php

namespace App\Http\Controllers\User\Action;

use App\Models\User;
use Helper\Response\Response;
use Helper\Type\Status\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait IndexUserAction {

    /**
     * Return a paginated list of users with optional filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexUsers(Request $request): JsonResponse {
        if (!$this->userCanSeeUser()) {
            return Response::_401();
        }

        $id = $request->input('id');
        $state = $request->input('state');
        $search = $request->input('search');
        $roleId = $request->input('role_id');

        $users = User::query()
            ->with('roles', 'userDetail')
            ->withCount([
                'userPermissionOverrides as permission_overrides_count' => fn ($query) => $query->withInActiveDateRange(),
            ])
            ->applyRoleBasedQuery(
                currentUserPermission: PERMISSION_SEE_USER,
                withUnassignedUsers: true,
                roleBindingQuery: function ($query) use ($roleId) {
                    $query->when($roleId, fn ($query) => $query->where('role_id', $roleId));
                },
            )
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orJsonbLangValueSearch('full_name', $search);
                    });
            })
            ->when($state !== null, fn ($query) => $query->where('state', $state))
            ->when($id, fn ($query) => $query->where('id', $id))
            // ---- "who can head THIS department" ----
            // A head is one of the department's own instructors, so the picker
            // asks for exactly that rather than the whole staff list. Reads
            // through `instructors.user_id`, the same link that gives an
            // instructor their departmental scope.
            ->when($request->input('instructor_of_department_id'), function ($query) use ($request) {
                $departmentId = (int) $request->input('instructor_of_department_id');

                $query->whereHas(
                    'instructor',
                    fn ($instructor) => $instructor->where('department_id', $departmentId)
                        ->where('is_active', true),
                );
            })
            ->orderBy('created_at', 'DESC')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $users->collection(),
            'statuses' => Status::idAndName(),
            'pagination' => User::extractPagination($users),
        ]);
    }
}
