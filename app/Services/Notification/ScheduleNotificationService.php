<?php

namespace App\Services\Notification;

use Common\Notification\ScheduleNotificationServiceInterface;
use Illuminate\Support\Facades\Log;

class ScheduleNotificationService implements ScheduleNotificationServiceInterface {

    public function __construct(
        private readonly ExternalNotificationClient $client,
        private readonly NotificationPayloadBuilder $builder,
    ) {
    }

    /**
     * Send a single templated notification to one recipient.
     *
     * @param array $recipient
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     * @throws \Throwable
     */
    public function sendTemplated(
        array $recipient,
        string $templateKey,
        array $data = [],
        array $channels = [],
        ?string $language = null,
    ): bool {
        try {
            $payload = $this->builder->buildSingle($recipient, $templateKey, $data, $channels, $language);
            return $this->client->sendSingle($payload);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Notification skipped: ' . $e->getMessage(), compact('templateKey', 'channels'));
            return false;
        } catch (\Throwable $e) {
            Log::error('Notification failed: ' . $e->getMessage(), compact('templateKey'));
            return false;
        }
    }

    /**
     * Send a bulk templated notification to multiple recipients.
     *
     * @param array $recipients
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     * @throws \Throwable
     */
    public function sendBulkTemplated(
        array $recipients,
        string $templateKey,
        array $data = [],
        array $channels = [],
        ?string $language = null,
    ): bool {
        try {
            $payload = $this->builder->buildBulk($recipients, $templateKey, $data, $channels, $language);
            return $this->client->sendBulk($payload);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Bulk notification skipped: ' . $e->getMessage(), compact('templateKey', 'channels'));
            return false;
        } catch (\Throwable $e) {
            Log::error('Bulk notification failed: ' . $e->getMessage(), compact('templateKey'));
            return false;
        }
    }
}
