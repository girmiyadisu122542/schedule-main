<?php

namespace App\Providers;

use App\Services\Notification\MailNotificationService;
use App\Services\Notification\NotificationTemplateRegistry;
use Common\Notification\ScheduleNotificationServiceInterface;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider {

    /**
     * Bind the notification contract to Laravel's mailer.
     *
     * This used to bind an HTTP client that posted to a separate notification
     * service owned by another system. That service is not part of this
     * deployment, so the binding is the ONE place that changed — every caller
     * still asks for `ScheduleNotificationServiceInterface` and is unaware.
     *
     * @return void
     */
    public function register(): void {
        $this->app->singleton(NotificationTemplateRegistry::class);

        $this->app->bind(ScheduleNotificationServiceInterface::class, function ($app) {
            return new MailNotificationService(
                $app->make(NotificationTemplateRegistry::class),
            );
        });
    }
}
