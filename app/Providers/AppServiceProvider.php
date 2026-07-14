<?php

namespace App\Providers;

use App\Validation\CustomValidator;
use Carbon\Carbon;
use Helper\Field\Field;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        $this->mergeNotificationRegistries();
        if (class_exists(Passport::class)) {
            Passport::tokensExpireIn(
                Carbon::now()->addDays(ACCESS_TOKEN_EXPIRE_DAYS)
            );

            Passport::refreshTokensExpireIn(
                Carbon::now()->addDays(REFRESH_TOKEN_EXPIRE_DAYS)
            );
        }

        // Register Field Macros
        Field::registerMacros();

        Validator::resolver(function ($translator, $data, $rules, $messages, $attributes) {
            return new CustomValidator($translator, $data, $rules, $messages, $attributes);
        });
    }

    private function mergeNotificationRegistries(): void {
        $mergedRegistry = $this->app['config']->get('notification_registry', []);

        foreach (array_keys($this->app['config']->all()) as $configKey) {
            if ($configKey === 'notification_registry' || !str_ends_with($configKey, 'notification_registry')) {
                continue;
            }

            $registry = $this->app['config']->get($configKey, []);
            if (is_array($registry) && $registry !== []) {
                $mergedRegistry = array_replace_recursive($mergedRegistry, $registry);
            }
        }

        $this->app['config']->set('notification_registry', $mergedRegistry);
    }
}
