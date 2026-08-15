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
     * Send login credentials to a newly created user.
     *
     * The password here is the one-time plaintext the account was created with;
     * it is hashed on the user row and exists nowhere else, so this message is
     * the only copy. A failure returns `false` rather than throwing — a mail
     * that did not send is not a reason to roll back the account it belongs to,
     * and the caller can offer to resend.
     *
     * `$method` comes before `$language` because it is required. The original
     * ordering — an optional `$language` ahead of a required `$method` — is a
     * PHP 8 deprecation, and made the parameter implicitly required anyway.
     *
     * @param array $credentials name, email, phone, password
     * @param int $method an OtpMethod constant
     * @param string|null $language recipient language key
     *
     * @return bool
     */
    public function send(array $credentials, int $method = OtpMethod::EMAIL, ?string $language = null): bool {
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
