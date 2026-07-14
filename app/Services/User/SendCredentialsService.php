<?php

namespace App\Services\User;

use App\Constants\Otp\OtpMethod;
use Common\Notification\ScheduleNotificationServiceInterface;

class SendCredentialsService {

    public function __construct(
        private readonly ScheduleNotificationServiceInterface $notificationService,
    ) {
    }

    /**
     * Send login credentials (email + password) to a newly created user.
     *
     * @param array $credentials
     * @param string|null $language
     * @param int $method
     *
     * @return bool
     */
    public function send(array $credentials, ?string $language = null, int $method): bool {
        $recipient = $method === OtpMethod::EMAIL
            ? ['email' => $credentials['email'] ?? null]
            : ['phone' => $credentials['phone'] ?? null];

        return $this->notificationService->sendTemplated(
            recipient: $recipient,
            templateKey: NOTIFICATION_TEMPLATE_KEY_USER_REGISTRATION,
            data: [
                'name' => $credentials['name'] ?? $credentials['email'],
                'full_name' => $credentials['name'] ?? $credentials['email'],
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ],
            channels: $method === OtpMethod::EMAIL
                ? [NOTIFICATION_SEND_METHOD_EMAIL]
                : [NOTIFICATION_SEND_METHOD_SMS],
            language: $language,
        );
    }
}
