<?php

namespace App\Http\Controllers\Auth;

use App\Constants\Otp\OtpMethod;
use App\Constants\Otp\OtpType;
use App\Helpers\OtpHelper;
use App\Http\Controllers\Controller;
use App\Models\Otp\Otp;
use App\Models\User;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Translation\Message;

class PasswordController extends Controller {

    /**
     * Send OTP to the user's email for password reset.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgetPassword(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::query()
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return Response::_200(Message::get('email_will_be_sent'));
        }

        $receiverName = $user->full_name__localized ?: $user->email;

        $response = OtpHelper::sendOtp(
            userId: $user->id,
            type: OtpType::RESET,
            method: OtpMethod::EMAIL,
            emailOrPhone: $user->email,
            receiverName: $receiverName,
        );

        if (!$response['success']) {
            return Response::_422(Message::get('otp_sent_failed'));
        }

        return Response::_200(Message::get('email_will_be_sent'));
    }

    /**
     * Verify OTP
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:' . OTP_LENGTH],
            'type' => ['required', Rule::in([OTpType::RESET, OtpType::REGISTER])],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::query()
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return Response::_404();
        }

        $otpType = (int) $request->type;

        $result = OtpHelper::verifyOtp(
            userId: $user->id,
            otpCode: $request->otp_code,
            type: $otpType,
            method: OtpMethod::EMAIL,
            deleteAfterVerify: false,
        );

        if (!$result['success']) {
            return Response::_422($result['message']);
        }

        if ($otpType === OtpType::REGISTER) {
            $user->email_verified_at = now();
            $user->save();
        }

        return Response::_200(['message' => Message::get('otp_verified_successfully')]);
    }

    /**
     * Reset the password.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'new_password' => ['required', 'string', 'min:' . PASSWORD_LENGTH, 'confirmed'],
            'new_password_confirmation' => ['required', 'string'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::query()
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return Response::_404();
        }

        $verifiedOtp = Otp::query()
            ->where('user_id', $user->id)
            ->where('type', OtpType::RESET)
            ->where('method', OtpMethod::EMAIL)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$verifiedOtp) {
            return Response::_422(Message::get('invalid_or_expired_reset_token'));
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $user->password = Hash::make($request->new_password);
            $user->tokens()->update(['revoked' => true]);
            $user->save();

            Otp::query()
                ->where('user_id', $user->id)
                ->where('type', OtpType::RESET)
                ->delete();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_reset_password'));
        }

        return Response::_200(Message::get('password_reset_successfully'));
    }

    /**
     * Resend OTP for reset or registration.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendOtp(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'type' => ['required', Rule::in([OTpType::RESET, OtpType::REGISTER])],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = User::query()
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return Response::_404();
        }

        $otpType = (int) $request->type;

        if ($otpType === OtpType::REGISTER && $user->email_verified_at) {
            return Response::_422(Message::get('user_already_verified'));
        }

        $otpType = (int) $request->type;
        $receiverName = $user->full_name__localized ?: $user->email;

        $response = OtpHelper::sendOtp(
            userId: $user->id,
            type: $otpType,
            method: OtpMethod::EMAIL,
            emailOrPhone: $user->email,
            receiverName: $receiverName,
        );

        if (!$response['success']) {
            return Response::_422(Message::get('otp_sent_failed'));
        }

        return Response::_200(Message::get('otp_sent_successfully'));
    }

    /**
     * Change password for the authenticated user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:' . PASSWORD_LENGTH, 'confirmed'],
            'new_password_confirmation' => ['required', 'string'],
        ]);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $user = Auth::user();
        if (!Hash::check($request->old_password, $user->password)) {
            return Response::_422(null, ['old_password' => [Message::get('invalid_password')]]);
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $user->password = Hash::make($request->new_password);
            $user->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_reset_password'));
        }

        return Response::_200(Message::get('password_reset_successfully'));
    }
}
