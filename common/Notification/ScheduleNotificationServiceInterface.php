<?php

namespace Common\Notification;

/**
 * Handles sending notifications via the external templated notification service.
 */
interface ScheduleNotificationServiceInterface {
    /**
     * Send a single templated notification.
     * -$recipient => Recipient contact data (email and/or phone)
     * -$templateKey => Internal template registry key
     * -$data Structured payload for the external template
     * -$channels =>  Optional channels to use
     * -$language =>  Optional language
     *
     * @param array $recipient
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     * @return bool
     */
    public function sendTemplated(array $recipient, string $templateKey, array $data = [], array $channels = [], ?string $language = null): bool;

    /**
     * Send a bulk templated notification.
     * -$recipients => List of recipients with email and/or phone
     * -$templateKey => Internal template registry key
     * -$data => Structured payload for the external template
     * -$channels => Optional channels to use
     * -$language => Optional language
     *
     * @param array $recipients
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     * @return bool
     */
    public function sendBulkTemplated(array $recipients, string $templateKey, array $data = [], array $channels = [], ?string $language = null): bool;
}
