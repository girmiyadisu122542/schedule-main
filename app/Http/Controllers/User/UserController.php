<?php

namespace App\Http\Controllers\User;

use App\Constants\Otp\OtpMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Action\CreateUserAction;
use App\Http\Controllers\User\Action\IndexUserAction;
use App\Http\Controllers\User\Action\UserProfileAction;
use App\Http\Requests\User\UserBulkOperationRequest;
use App\Models\User;
use App\Models\User\UserLog;
use App\Services\User\SendCredentialsService;
use Constants\AppConstant;
use Helper\Response\Response;
use Helper\Type\State\State;
use Helper\Type\Status\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
    /**
     * Issue a fresh password and email it to the user.
     *
     * For the ordinary case where somebody never received their credentials,
     * or lost them: rather than an admin reading a password out to someone, the
     * account gets a new one and the same registration mail carries it.
     *
     * The old password stops working the moment this succeeds — that is the
     * point, not a side effect. A password that was possibly seen by the wrong
     * person should not survive the resend meant to replace it.
     *
     * If the mail cannot be sent the change is rolled back, so the user is
     * never left holding a password that was never delivered. That is the one
     * place this differs from account creation, where a failed mail leaves the
     * account standing because the account is worth more than the message.
     *
     * @param \Illuminate\Http\Request $request
     * @param int|string $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendPassword(Request $request, $userId): JsonResponse {
        if (!$this->userCanUpdateUser()) {
            return Response::_401();
        }

        // `withUnassignedUsers` matches indexUsers(): a user with no role yet is
        // exactly the sort who never got their credentials, and omitting this
        // made them un-resendable — a 404 on a user plainly sitting in the list.
        $user = User::query()
            ->applyRoleBasedQuery(
                currentUserPermission: PERMISSION_UPDATE_USER,
                withUnassignedUsers: true,
            )
            ->find($userId);

        if (!$user) {
            return Response::_404(Message::get('user_not_found'));
        }

        if (!$user->email) {
            return Response::_422(Message::get('user_has_no_email'));
        }

        $password = Str::password(PASSWORD_LENGTH);
        $previous = $user->password;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $user->password = Hash::make($password);
            $user->save();

            $sent = app(SendCredentialsService::class)->send(
                [
                    'name' => $user->full_name__localized ?? $user->email,
                    'email' => $user->email,
                    'password' => $password,
                    'phone' => $user->phone ?? null,
                ],
                OtpMethod::EMAIL,
                getCurrentLanguage($request),
            );

            if (!$sent) {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
                $user->password = $previous;

                return Response::_422(Message::get('credentials_could_not_be_sent'));
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return Response::_200([
            'message' => Message::get('password_resent', ['email' => $user->email]),
        ]);
    }

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
