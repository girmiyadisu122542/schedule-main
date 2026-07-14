<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\User\LoginAttempt;
use App\Models\User\UserLog;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use Translation\Message;

class LoginController extends Controller {

    /**
     * Add new login attempt
     * and returns the model
     *
     * @param string $username
     * @param string $userIp
     * @param string $userAgent
     *
     * @return \App\Models\User\LoginAttempt
     */
    public function addNewLoginAttempt($username, $userIp, $userAgent): LoginAttempt {
        $loginAttempt = new LoginAttempt();
        $loginAttempt->ip = $userIp;
        $loginAttempt->username = $username;
        $loginAttempt->user_agent = $userAgent;
        $loginAttempt->save();

        return $loginAttempt;
    }

    /**
     * Clears login attempts
     *
     * @param $username
     * @return void
     */
    public function clearLoginAttempts($username): void {
        LoginAttempt::query()->where('username', $username)->delete();
    }

    /**
     * Adds User log
     * and returns the model
     *
     * @param int $userId
     * @param string $userIp
     * @param string $userAgent
     * @param string $device
     * @param string|null $loginTime
     * @param string|null $loginTime
     * @param string|null $reason
     *
     * @return \App\Models\User\UserLog
     */
    public function addUserLog($userId, $userIp, $userAgent, $device, $loginTime = null, $logoutTime = null, $reason = null): UserLog {
        $userLog = new UserLog();
        $userLog->ip = $userIp;
        $userLog->device = $device;
        $userLog->reason = $reason;
        $userLog->user_id = $userId;
        $userLog->user_agent = $userAgent;
        $userLog->login_time = $loginTime;
        $userLog->logout_time = $logoutTime;
        $userLog->save();

        return $userLog;
    }

    /**
     * Fetches the number of latest login attempts
     * in the range of login lock timeout
     *
     * @param string $username
     * @return int
     */
    public function getRecentLoginAttempts($username): int {
        $lockTimeout = LOGIN_LOCK_TIMEOUT;
        $currentTimeMinusLockTimeout = date("Y-m-d H:i:s", strtotime("-$lockTimeout seconds"));

        $attemptCounts = LoginAttempt::query()
            ->where('username', $username)
            ->where('created_at', '>=', $currentTimeMinusLockTimeout)
            ->count();

        return $attemptCounts;
    }

    /**
     * Authenticates the user using passport authentication
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function login(Request $request): mixed {
        $rules = [
            'username' => ['required'],
            'password' => ['required'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $username = $request->username;
        $attempts = $this->getRecentLoginAttempts($username);
        if ($attempts >= MAXIMUM_LOGIN_ATTEMPT) {
            return Response::_422(Message::get('too_many_login_attempts'));
        }

        $credentials = [
            'email' => request()->username,
            'password' => request()->password,
        ];

        $userIp = request()->server('REMOTE_ADDR');
        $userAgent = request()->server('HTTP_USER_AGENT');

        if (!Auth::attempt($credentials)) {
            $this->addNewLoginAttempt($username, $userIp, $userAgent);
            return Response::_422(Message::get('invalid_credentials'));
        }

        $agent = new Agent();
        $device = $agent->device();
        $loginTime = date('Y-m-d H:i:s');

        $user = auth()->user();

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $this->clearLoginAttempts($username);
            $this->addUserLog($user->id, $userIp, $userAgent, $device, $loginTime);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_login_please_contact_administrator'));
        }

        if ($user->mfa_enabled) {
            return Response::_200([
                'mfa_required' => true,
                'email' => $user->email,
            ]);
        }

        $tokenResult = $user->createToken($username);
        $deviceUuid = sha1($userAgent . '|' . $userIp);

        $token = $tokenResult->token;
        $token->last_used_at = now();
        $token->ip_address = $userIp;
        $token->device_name = $device;
        $token->user_agent = $userAgent;
        $token->device_uuid = $deviceUuid;
        $token->save();

        return Response::_200([
            'token' => $tokenResult->accessToken,
        ]);
    }

    /**
     * Signs out the user from the system
     * by revoking the current passport token
     * and records the logout time in user log
     *
     * @return mixed
     */
    public function logout(): mixed {
        $user = Auth::user();

        if ($user) {
            $userIp = request()->server('REMOTE_ADDR');
            $userAgent = request()->server('HTTP_USER_AGENT');
            $logoutTime = date('Y-m-d H:i:s');

            $agent = new Agent();
            $device = $agent->device();

            try {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

                $latestUserLog = UserLog::query()
                    ->where('user_id', $user->id)
                    ->whereNull('logout_time')
                    ->latest('login_time')
                    ->first();

                if ($latestUserLog) {
                    $latestUserLog->logout_time = $logoutTime;
                    $latestUserLog->save();
                } else {
                    $this->addUserLog($user->id, $userIp, $userAgent, $device, null, $logoutTime, Message::get('auto_created_on_logout'));
                }

                $user->tokens()->update(['revoked' => true]);

                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
            } catch (Exception $e) {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
                return Response::_422(Message::get('logout_completed_but_logging_failed'));
            }
        }

        $response = ['message' => Message::get('successfully_loggedout')];
        return Response::_200($response);
    }


    /**
     * Force logout a user by revoking all tokens and sessions
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceLogout($userId): mixed {
        if (!$this->userCanManageUserSessions()) {
            return Response::_401();
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $user = User::find($userId);
            if (!$user) {
                return Response::_404(['message' => Message::get('user_not_found')]);
            }

            $user->tokens()->update(['revoked' => true]);


            DB::table('sessions')
                ->where('user_id', $userId)
                ->delete();

            $this->addUserLog(
                $userId,
                $user->last_known_ip ?? 'N/A',
                $user->last_known_user_agent ?? 'N/A',
                'Unknown',
                null,
                now(),
                'Force logout by admin'
            );

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
            return Response::_200(['message' => Message::get('user_logged_out_successfully')]);
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            return Response::_500(['error' => Message::get('unable_to_logout_user')]);
        }
    }
}
