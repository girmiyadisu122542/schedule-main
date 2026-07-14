<?php

namespace App\Http\Controllers\Role\Action;

use App\Http\Requests\Role\UserRoleBindingRequest;
use App\Models\Role\Role;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Helper\Cache\RoleCacheHandler;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Translation\Message;

trait UserRoleBindingAction {
    /**
     * Assign role to a user
     *
     * @param \App\Http\Requests\Role\UserRoleBindingRequest $request
     * @param int $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRoleToUser(UserRoleBindingRequest $request, $userId): JsonResponse {
        if (!$this->userCanAssignRoleToUser()) {
            return Response::_403();
        }

        $role = Role::query()
            ->applyRoleBasedQuery(permissionKey: PERMISSION_ASSIGN_ROLE_TO_USER)
            ->where('state', STATE_ACTIVE)
            ->find(request()->role_id);

        if (!$role) {
            return Response::_404(Message::get('role_could_not_be_found'));
        }

        $user = User::query()
            ->whereNot('id', Auth::id())
            ->find($userId);

        if (!$user) {
            return Response::_404(Message::get('user_could_not_be_found'));
        }

        if ($user->state != USER_STATE_ACTIVE) {
            return Response::_422(Message::get('cannot_assign_role_to_inactive_user'));
        }

        $validated = $request->validated();
        $bindingId = $validated['binding_id'] ?? null;
        if ($role->unique_per_user) {
            $hasUserRole = UserRoleBinding::query();
            if ($request->has('ends_at')) {
                $hasUserRole
                    ->where(function ($query) use ($validated) {
                        $query
                            ->orWhereNull('ends_at')
                            ->orWhereBetween('ends_at', [$validated['starts_at'], $validated['ends_at']])
                            ->orWhereBetween('starts_at', [$validated['starts_at'], $validated['ends_at']]);
                    });
            } else {
                $hasUserRole->withInActiveDateRange();
            }

            $hasUserRole = $hasUserRole
                ->when($bindingId, fn ($query) => $query->where('id', '!=', $bindingId))
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->exists();

            if ($hasUserRole) {
                $bindings = [
                    'role' => $role->name__localized,
                    'user' => $user->full_name__localized,
                ];

                return Response::_422(Message::get('you_can_not_assign_this_role_multiple_times', $bindings));
            }
        }

        $userRoleBinding = null;
        if ($bindingId) {
            $userRoleBinding = UserRoleBinding::query()
                ->where('user_id', $userId)
                ->find($bindingId);

            if (!$userRoleBinding) {
                return Response::_404(Message::get('assigned_role_could_not_be_found'));
            }
        }

        if (!$userRoleBinding) {
            $userRoleBinding = UserRoleBinding::query()
                ->withInActiveDateRange()
                ->where('role_id', $role->id)
                ->where('user_id', $userId)
                ->first();
        }

        if (!$userRoleBinding) {
            $userRoleBinding = new UserRoleBinding();
            $userRoleBinding->user_id = $userId;
        }

        $userRoleBinding->role_id = $validated['role_id'];

        $bindings = [
            'role' => $role->name__localized,
            'user' => $user->full_name__localized,
        ];

        try {
            $userRoleBinding->assigned_by = Auth::id();
            $userRoleBinding->ends_at = $validated['ends_at'] ?? null;
            $userRoleBinding->starts_at = Carbon::parse($validated['starts_at']);
            $userRoleBinding->save();
        } catch (Exception $exception) {
            $message = $exception->getMessage();
            $errorKey = 'unable_to_assign_role_to_user';
            if (str_contains($message, USER_ROLE_UNIQUE_KEY)) {
                $errorKey = 'role_is_already_assigned_to_user';
            }

            return Response::_422(Message::get($errorKey, $bindings));
        }

        // ToDo: Needs to be in a scheduler
        RoleCacheHandler::updateUserCacheFromBinding($userRoleBinding);

        return Response::_200([
            'message' => Message::get('role_assigned_to_user', $bindings),
        ]);
    }

    /**
     * Revoke a user's role binding
     *
     * @param int $bindingId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeUserRoleBinding($bindingId): JsonResponse {
        if (!$this->userCanRemoveRoleFromUser()) {
            return Response::_403();
        }

        $userRoleBinding = UserRoleBinding::find($bindingId);
        if (!$userRoleBinding) {
            return Response::_404(Message::get('role_binding_not_found'));
        }

        try {
            $userRoleBinding->delete();
        } catch (Exception $exception) {
            return Response::_422(Message::get('unable_to_revoke_role_binding'));
        }

        return Response::_200([
            'message' => Message::get('role_binding_revoked'),
        ]);
    }

    /**
     * Return a single user role binding as an editable form payload.
     *
     * @param int $bindingId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRoleBinding($bindingId): JsonResponse {
        if (!$this->userCanAssignRoleToUser()) {
            return Response::_403();
        }

        $binding = UserRoleBinding::find($bindingId);
        if (!$binding) {
            return Response::_404(Message::get('role_binding_not_found'));
        }

        return Response::_200([
            'form' => $binding->resource('formFields'),
        ]);
    }
}
