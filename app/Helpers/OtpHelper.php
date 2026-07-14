<?php

namespace App\Helpers;

use App\Constants\Otp\OtpMethod;
use App\Constants\Otp\OtpType;
use App\Models\Otp\Otp;
use Carbon\Carbon;
use Common\Notification\ScheduleNotificationServiceInterface;
use Constants\AppConstant;
use Illuminate\Support\Facades\DB;
use Translation\Message;

class OtpHelper {

    /**
     * Generate, store, and send an OTP.
     * Channel and recipient normalization is handled by the notification service.
     *
     * @param int $userId
     * @param int $type
     * @param int $method
     * @param string $emailOrPhone
     * @param string $receiverName
     * @param $length
     * @param $expiryMinutes
     *
     * @return array
     */
    public static function sendOtp(
        int $userId,
        int $type,
        int $method,
        string $emailOrPhone,
        string $receiverName,
        int $length = OTP_LENGTH,
        int $expiryMinutes = OTP_EXPIRATION_TIME,
    ): array {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            Otp::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('method', $method)
                ->delete();

            $otpCode = self::generateOtp($length);

            Otp::create([
                'user_id' => $userId,
                'type' => $type,
                'method' => $method,
                'otp_code' => $otpCode,
                'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
            ]);


            $recipient = $method === OtpMethod::EMAIL
                ? ['email' => $emailOrPhone]
                : ['phone' => $emailOrPhone];

            $notificationSent = app(ScheduleNotificationServiceInterface::class)->sendTemplated(
                recipient: $recipient,
                templateKey: NOTIFICATION_TEMPLATE_KEY_OTP,
                data: [
                    'name' => $receiverName,
                    'otp' => $otpCode,
                    'message' => self::resolveOtpMessage($type),
                    'time' => $expiryMinutes,
                ],
            );

            if (!$notificationSent) {
                return [
                    'success' => false,
                    'message' => Message::get('otp_sent_failed'),
                    'status_code' => 500,
                ];
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return [
                'success' => false,
                'message' => Message::get('otp_sent_failed') . ' ' . $e->getMessage(),
                'status_code' => 500,
            ];
        }

        return [
            'success' => $notificationSent,
            'message' => Message::get('otp_sent_successfully'),
        ];
    }

    /**
     * Verify an OTP.
     *
     * @param int $userId
     * @param string $otpCode
     * @param int $type
     * @param int $method
     * @param bool $deleteAfterVerify
     *
     * @return array
     */
    public static function verifyOtp(
        int $userId,
        string $otpCode,
        int $type,
        int $method,
        bool $deleteAfterVerify = true,
    ): array {
        $otp = Otp::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('method', $method)
            ->where('otp_code', $otpCode)
            ->first();

        if (!$otp) {
            return [
                'success' => false,
                'message' => Message::get('invalid_otp'),
            ];
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            if (Carbon::now()->greaterThan($otp->expires_at)) {
                return ['success' => false, 'message' => Message::get('otp_expired')];
            }

            $otp->update(['verified_at' => now()]);

            if ($deleteAfterVerify) {
                $otp->delete();
            }
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return ['success' => false, 'message' => Message::get('otp_verification_failed')];
        }

        return ['success' => true, 'message' => Message::get('otp_verified_successfully')];
    }

    /**
     * Returns the context message for the OTP type,
     * passed as {message} in the shared template.
     *
     * @param int $type
     * @return string
     */
    private static function resolveOtpMessage(int $type): string {
        return Message::get(match ($type) {
            OtpType::REGISTER => 'otp_message_register',
            OtpType::LOGIN_WITH_OTP => 'otp_message_login',
            OtpType::RESET => 'otp_message_reset',
            OtpType::TWO_FA_ENABLE => 'otp_message_two_fa_enable',
        });
    }

    /**
     * Generates a random OTP code of a given length.
     *
     * @param int $length
     * @return string
     */
    private static function generateOtp(int $length): string {
        return str_pad((string) mt_rand(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);
    }
}
