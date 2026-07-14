<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Action\CreateUserAction;
use App\Http\Controllers\User\Action\IndexUserAction;
use App\Http\Controllers\User\Action\UserProfileAction;
use App\Http\Requests\User\UserBulkOperationRequest;
use App\Models\User;
use App\Models\User\UserLog;
use Helper\Response\Response;
use Helper\Type\State\State;
use Helper\Type\Status\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class UserController extends Controller {
    use CreateUserAction, IndexUserAction, UserProfileAction;

    /**
     * Return authenticated user data
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authUser(Request $request): JsonResponse {
        if (!Auth::check()) {
            return Response::_401();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userData = $user->resource('authUserFields');

        return Response::_200([
            'data' => $userData,
            'message' => Message::get('profile_fetched_successfully'),
        ]);
    }

    /**
     * Change the state of the user between Active and Inactive
     *
     * @param \Illuminate\Http\Request $request
     * @param int $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState(Request $request, $userId): JsonResponse {
        if (!$this->userCanChangeUserState()) {
            return Response::_401();
        }

        $user = User::query()
            ->applyRoleBasedQuery(
                currentUserPermission: PERMISSION_CHANGE_USER_STATE,
                withUnassignedUsers: true,
            )
            ->find($userId);

        if (!$user) {
            return Response::_404();
        }

        $rules = [
            'state' => ['required', State::ruleIn()],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('user'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($user->state == request()->state) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $user->state = request()->state;
        $user->save();

        $bindings = ['name' => $user->full_name__localized];

        $message = request()->state == STATE_ACTIVE
            ? 'user_successfully_activated'
            : 'user_successfully_deactivated';

        $userData = $user->resource('indexFields');


        return Response::_200([
            'data' => $userData,
            'message' => Message::get($message, $bindings),
        ]);
    }

    /**
     * Change the status of the user
     *
     * @param \Illuminate\Http\Request $request
     * @param int $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus(Request $request, $userId): JsonResponse {
        if (!$this->userCanChangeUserStatus()) {
            return Response::_401();
        }

        $user = User::query()
            ->applyRoleBasedQuery(
                currentUserPermission: PERMISSION_CHANGE_USER_STATUS,
                withUnassignedUsers: true,
            )
            ->find($userId);

        if (!$user) {
            return Response::_404();
        }

        $rules = [
            'status' => ['required', Status::ruleIn()],
        ];

        $validator = Validator::make($request->all(), $rules, Message::get('user'));
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($user->status == request()->status) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $user->status = request()->status;
        $user->save();

        $bindings = ['name' => $user->full_name__localized];

        return Response::_200([
            'data' => $user->resource(),
            'message' => Message::get('user_status_successfully_changed', $bindings),
        ]);
    }

    /**
     * Change the state of the user between Active and Inactive
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserLogs($userId): JsonResponse {
        if (!$this->userCanSeeUser()) {
            return Response::_401();
        }

        $user = User::query()
            ->applyRoleBasedQuery(
                currentUserPermission: PERMISSION_SEE_USER,
                withUnassignedUsers: true,
            )
            ->find($userId);

        if (!$user) {
            return Response::_404();
        }

        $logs = $user->userLogs()->with('user')->paginate(static::getPerPage());

        return Response::_200([
            'data' => $logs->collection(),
            'pagination' => UserLog::extractPagination($logs),
        ]);
    }

    /**
     * Change the state of the user between Active and Inactive
     *
     * @param \App\Http\Requests\User\UserBulkOperationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleBulkAction(UserBulkOperationRequest $request): JsonResponse {
        if (!$this->userCanChangeUserState() && !$this->userCanDeleteUser()) {
            return Response::_401();
        }

        $userIds = $request->user_ids;
        $actionType = $request->action_type;
        $message = 'action_not_found';

        try {
            if ($actionType == ACTION_ACTIVATE) {
                $message = 'users_activated_successfully';
                User::query()
                    ->applyRoleBasedQuery(
                        currentUserPermission: PERMISSION_CHANGE_USER_STATE,
                        withUnassignedUsers: true,
                    )
                    ->whereIn('id', $userIds)
                    ->update(['state' => STATE_ACTIVE]);
            } else if ($actionType == ACTION_DEACTIVATE) {
                $message = 'users_deactivated_successfully';
                User::query()
                    ->applyRoleBasedQuery(
                        currentUserPermission: PERMISSION_CHANGE_USER_STATE,
                        withUnassignedUsers: true,
                    )
                    ->whereIn('id', $userIds)
                    ->update(['state' => STATE_INACTIVE]);
            }
        } catch (\Exception $exception) {
            return Response::_500(Message::get('bulk_action_failed'));
        }

        return Response::_200([
            'message' => Message::get($message),
        ]);

    }
}
