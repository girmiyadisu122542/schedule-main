<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalNotificationClient {

    /**
     * Sends a single templated notification using the external notification service.
     *
     * @param array $payload
     * @return bool
     */
    public function sendSingle(array $payload): bool {
        return $this->send(
            config('notification_service.api.single_endpoint'),
            $payload,
            'single'
        );
    }

    /**
     * Sends a bulk templated notification using the external notification service.
     *
     * @param array $payload
     * @return bool
     */
    public function sendBulk(array $payload): bool {
        return $this->send(
            config('notification_service.api.bulk_endpoint'),
            $payload,
            'bulk'
        );
    }

    /**
     * Sends a templated notification using the external notification service.
     *
     * @param ?string $endpoint
     * @param array $payload
     * @param string $type
     * @return bool
     */
    private function send(?string $endpoint, array $payload, string $type): bool {
        if (!$endpoint) {
            Log::warning('Notification endpoint is not configured', ['type' => $type]);
            return false;
        }

        $request = Http::timeout((int) config('notification_service.api.timeout', 10))
            ->acceptJson();

        $apiKey = config('notification_service.api.api_key');
        if (!empty($apiKey)) {
            $request = $request->withHeaders([
                'X-API-Key' => $apiKey,
            ]);
        }

        $response = $request->post($endpoint, $payload);

        if ($response->successful()) {
            return true;
        }

        Log::error('External notification request failed', [
            'type' => $type,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return false;
    }
}
