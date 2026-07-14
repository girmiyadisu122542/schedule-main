<?php

namespace App\Http\Controllers\Auth;

use App\Constants\Otp\OtpMethod;
use App\Constants\Otp\OtpType;
use App\Helpers\OtpHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\User\User2FA;
use App\Models\User\UserBackupCode;
use App\Models\User\UserLog;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use Translation\Message;

class TwoFactorController extends Controller {

    /**
     * Return the current user's MFA status and all backup codes with their status.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userMFAStatus(): JsonResponse {
        $user = Auth::user();
        $backupCodes = UserBackupCode::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', USER_BACKUP_CODE_DISCARDED)
            ->orderBy('id')
            ->get(['code', 'status']);

        $backupCodeFields = $backupCodes->collection();
        return Response::_200([
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'backup_codes' => $backupCodeFields,
        ]);
    }

    /**
     * Enable 2FA with password confirmation
     * Generates backup codes immediately on success.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enableMFA(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        /** @var \App\Models\User */
        $user = Auth::user();
        if (!Hash::check($request->password, $user->password)) {
            return Response::_422(null, ['password' => [Message::get('invalid_password')]]);
        }

        if ($user->mfa_enabled) {
            return Response::_422(Message::get('two_fa_already_enabled'));
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            User2FA::updateOrCreate([
                'user_id' => $user->id,
                'type' => OtpMethod::EMAIL,
            ], [
                'state' => STATE_ACTIVE,
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            $user->mfa_enabled = true;
            $user->save();

            $this->discardBackupCodes($user->id);
            $backupCodes = $this->generateBackupCodes($user->id);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            return Response::_422(Message::get('unable_to_enable_two_fa'));
        }

        $backupCodes = collect($backupCodes)->map(fn ($value) => ['value' => $value, 'used' => false]);

        return Response::_200([
            'backup_codes' => $backupCodes,
            'message' => Message::get('two_fa_enabled_successfully'),
        ]);
    }

    /**
     * Disable 2FA. Requires the current password for confirmation.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disable(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        /** @var \App\Models\User */
        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return Response::_422(Message::get('invalid_password'));
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            User2FA::query()
                ->where('user_id', $user->id)
                ->update([
                    'state' => STATE_INACTIVE,
                    'is_primary' => false,
                ]);

            $this->discardBackupCodes($user->id);

            $user->mfa_enabled = false;
            $user->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            return Response::_422(Message::get('unable_to_disable_two_fa'));
        }

        return Response::_200(Message::get('two_fa_disabled_successfully'));
    }

    /**
     * Regenerate backup codes.
     * Keeps used codes, discards unused ones, generates new ones to fill up to NUMBER_OF_BACKUP_CODES total.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateBackupCodes(): JsonResponse {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->mfa_enabled) {
            return Response::_422(Message::get('two_fa_not_enabled'));
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $total = NUMBER_OF_BACKUP_CODES;
            $this->discardBackupCodes($user->id);

            $usedCount = UserBackupCode::query()
                ->where('user_id', $user->id)
                ->where('status', USER_BACKUP_CODE_USED)
                ->count();

            $toGenerate = max(0, $total - $usedCount);
            $this->generateBackupCodes($user->id, $toGenerate);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_regenerate_backup_codes'));
        }

        $backupCodes = UserBackupCode::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [USER_BACKUP_CODE_NEW, USER_BACKUP_CODE_USED])
            ->orderBy('id')
            ->get(['code', 'status']);

        $allCodes = $backupCodes->collection();
        return Response::_200([
            'backup_codes' => $allCodes,
            'message' => Message::get('backup_codes_regenerated'),
        ]);
    }

    /**
     * Send a login OTP to the user's email.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLoginOtp(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->mfa_enabled) {
            return Response::_422(Message::get('invalid_or_expired_mfa_token'));
        }

        $receiverName = $user->full_name_localized ?? $user->email;

        $response = OtpHelper::sendOtp(
            userId: $user->id,
            type: OtpType::LOGIN_WITH_OTP,
            method: OtpMethod::EMAIL,
            emailOrPhone: $user->email,
            receiverName: $receiverName,
        );

        if (!$response['success']) {
            return Response::_422($response['message']);
        }

        return Response::_200($response['message']);
    }

    /**
     * Verify 2FA OTP during login and issue the real access token.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyLoginOtp(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:' . OTP_LENGTH],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->mfa_enabled) {
            return Response::_422(Message::get('invalid_or_expired_mfa_token'));
        }

        $result = OtpHelper::verifyOtp(
            userId: $user->id,
            otpCode: $request->otp_code,
            type: OtpType::LOGIN_WITH_OTP,
            method: OtpMethod::EMAIL,
        );

        if (!$result['success']) {
            return Response::_422($result['message']);
        }

        try {
            $token = $this->issueToken($user);
        } catch (Exception $e) {
            return Response::_422(Message::get('unable_to_login_please_contact_administrator'));
        }

        return Response::_200(['token' => $token]);
    }

    /**
     * Verify a backup code during login and issue the real access token.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyBackupCode(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'backup_code' => ['required', 'string'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::query()
            ->where('email', $request->email)
            ->first();
        if (!$user || !$user->mfa_enabled) {
            return Response::_422(Message::get('invalid_or_expired_mfa_token'));
        }

        $backupCode = UserBackupCode::query()
            ->where('status', USER_BACKUP_CODE_NEW)
            ->where('code', $request->backup_code)
            ->where('user_id', $user->id)
            ->first();

        if (!$backupCode) {
            return Response::_422(Message::get('invalid_backup_code'));
        }

        $backupCode->status = USER_BACKUP_CODE_USED;
        $backupCode->save();

        try {
            $token = $this->issueToken($user);
        } catch (Exception $e) {
            return Response::_422(Message::get('unable_to_login_please_contact_administrator'));
        }

        return Response::_200(['token' => $token]);
    }

    /**
     * Generate backup codes
     *
     * @param int $userId
     * @param int|null $count
     *
     * @return array
     */
    private function generateBackupCodes(int $userId, ?int $count = null): array {
        $plainCodes = [];
        $count = NUMBER_OF_BACKUP_CODES;
        $length = BACKUP_CODE_LENGTH;

        for ($i = 0; $i < $count; $i++) {
            $code = str_pad((string) random_int(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);
            $plainCodes[] = $code;

            UserBackupCode::create([
                'user_id' => $userId,
                'code' => $code,
                'status' => USER_BACKUP_CODE_NEW,
            ]);
        }

        return $plainCodes;
    }

    /**
     * Mark unused backup codes as discarded instead of deleting them.
     *
     * @param int $userId
     * @return void
     */
    private function discardBackupCodes(int $userId): void {
        UserBackupCode::query()
            ->where('user_id', $userId)
            ->where('status', USER_BACKUP_CODE_NEW)
            ->update(['status' => USER_BACKUP_CODE_DISCARDED]);
    }

    /**
     * Issue the real access token and log the login.
     *
     * @param \App\Models\User\User $user
     * @return string
     *
     * @throws \Exception
     */
    private function issueToken(User $user): string {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $agent = new Agent();
            $userLog = new UserLog();
            $userLog->ip = request()->server('REMOTE_ADDR');
            $userLog->device = $agent->device();
            $userLog->user_agent = request()->server('HTTP_USER_AGENT');
            $userLog->user_id = $user->id;
            $userLog->login_time = now();
            $userLog->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $e;
        }

        return $user->createToken($user->email)->accessToken;
    }
}
