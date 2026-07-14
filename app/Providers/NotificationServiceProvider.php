<?php

namespace App\Providers;

use App\Services\Notification\ExternalNotificationClient;
use App\Services\Notification\ScheduleNotificationService;
use App\Services\Notification\NotificationPayloadBuilder;
use App\Services\Notification\NotificationTemplateRegistry;
use Common\Notification\ScheduleNotificationServiceInterface;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton(NotificationTemplateRegistry::class);
        $this->app->singleton(ExternalNotificationClient::class);

        $this->app->singleton(NotificationPayloadBuilder::class, function ($app) {
            return new NotificationPayloadBuilder(
                $app->make(NotificationTemplateRegistry::class)
            );
        });

        $this->app->bind(ScheduleNotificationServiceInterface::class, function ($app) {
            return new ScheduleNotificationService(
                $app->make(ExternalNotificationClient::class),
                $app->make(NotificationPayloadBuilder::class),
            );
        });
    }
}
