<?php

namespace App\Providers;

use App\Services\UserService;
use Common\Contracts\UserServiceInterface;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider {

    /**
     * Register User module application services.
     */
    public function register() {
        $this->app->bind(UserServiceInterface::class, UserService::class);
    }
}