<?php

namespace App\Services\Notification;

use InvalidArgumentException;

class NotificationPayloadBuilder {
    public function __construct(
        private readonly NotificationTemplateRegistry $registry
    ) {
    }

    /**
     * Builds a single templated notification payload.
     *
     * @param array $recipient
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function buildSingle(
        array $recipient,
        string $templateKey,
        array $data = [],
        array $channels = [],
        ?string $language = null
    ): array {
        $recipient = $this->normalizeRecipient($recipient);
        $channels = $this->resolveChannels([$recipient], $channels);
        $data = $this->normalizeData($data);

        $this->registry->validateRequiredData($templateKey, $data);

        return [
            'channels' => $channels,
            'recipient' => $recipient,
            'template' => $this->registry->resolveChannelTemplates($templateKey, $channels, $language),
            'data' => $data,
        ];
    }

    /**
     * Builds a bulk templated notification payload.
     *
     * @param array $recipients
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function buildBulk(
        array $recipients,
        string $templateKey,
        array $data = [],
        array $channels = [],
        ?string $language = null
    ): array {
        $recipients = array_values(array_map(
            fn (array $recipient) => $this->normalizeRecipient($recipient),
            $recipients
        ));

        if ($recipients === []) {
            throw new InvalidArgumentException('Bulk notification requires at least one recipient.');
        }

        $channels = $this->resolveChannels($recipients, $channels);
        $data = $this->normalizeData($data);

        $this->registry->validateRequiredData($templateKey, $data);

        return [
            'channels' => $channels,
            'recipients' => $recipients,
            'template' => $this->registry->resolveChannelTemplates($templateKey, $channels, $language),
            'data' => $data,
        ];
    }

    /**
     * Normalizes a notification recipient by filtering out empty channel addresses.
     *
     * @param array $recipient
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function normalizeRecipient(array $recipient): array {
        $normalized = array_filter([
            'email' => $recipient['email'] ?? null,
            'phone' => $recipient['phone'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($normalized === []) {
            throw new InvalidArgumentException('Notification recipient must include at least one channel address.');
        }

        return $normalized;
    }

    /**
     * Resolves the channels for a notification payload by considering the supported channels,
     * requested channels and available channels for each recipient.
     *
     * @param array $recipients
     * @param array $channels
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function resolveChannels(array $recipients, array $channels): array {
        $supportedChannels = config('notification_service.channels', []);
        $channels = $channels === []
            ? $supportedChannels
            : array_values(array_intersect($channels, $supportedChannels));

        $availableChannels = [];
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                $availableChannels[] = NOTIFICATION_SEND_METHOD_EMAIL;
            }
            if (!empty($recipient['phone'])) {
                $availableChannels[] = NOTIFICATION_SEND_METHOD_SMS;
            }
        }

        $availableChannels = array_values(array_unique($availableChannels));
        $resolved = array_values(array_intersect($channels, $availableChannels));

        if ($resolved === []) {
            throw new InvalidArgumentException('Notification payload has no deliverable channels.');
        }

        return $resolved;
    }

    private function normalizeData(array $data): array {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
