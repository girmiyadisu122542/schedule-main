<?php

namespace Constants;

/**
 * Application Configuration Utilities
 */
class AppConfig {
    /**
     * Get the queue connection based on environment
     * Uses Redis if available, otherwise falls back to database
     */
    public static function getQueueConnection(): string {
        if (self::isRedisAvailable() && USE_HORIZON_FOR_QUEUE) {
            return QUEUE_CONNECTION_REDIS;
        }

        if (app()->environment('testing')) {
            return QUEUE_CONNECTION_SYNC;
        }

        return QUEUE_CONNECTION_DATABASE;
    }

    /**
     * Check if Redis is available
     */
    public static function isRedisAvailable(): bool {
        return CAN_USE_REDIS;
    }

    /**
     * Get queue driver for Horizon (only when Redis is available)
     */
    public static function shouldUseHorizon(): bool {
        return self::isRedisAvailable() &&
               !app()->environment('testing') && USE_HORIZON_FOR_QUEUE;
    }

    /**
     * Get the appropriate queue name based on priority and type
     */
    public static function getQueueName(string $type = QUEUE_DEFAULT, string $priority = QUEUE_DEFAULT): string {
        if ($priority === QUEUE_HIGH_PRIORITY) {
            return QUEUE_HIGH_PRIORITY;
        }

        if ($priority === QUEUE_LOW_PRIORITY) {
            return QUEUE_LOW_PRIORITY;
        }

        return $type;
    }

    /**
     * Returns sentry dsn or null based on sentry status.
     */
    public static function getSentryDNS() {
        return SENTRY_ENABLED ? SENTRY_DSN : null;
    }

    /**
     * Returns settings related to job batching information
     */
    public static function getJobBatchingSettings() {
        return [
            'database' => AppConstant::SCHEDULE_DATABASE,
            'table' => 'job_batches',
        ];
    }

    /**
     * Returns settings related to failed jobs
     */
    public static function getFailedJobSettings() {
        return [
            'driver' => 'database-uuids',
            'database' => AppConstant::SCHEDULE_DATABASE,
            'table' => 'failed_jobs',
        ];
    }
}
