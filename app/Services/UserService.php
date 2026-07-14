<?php

namespace App\Services;

use Common\Contracts\UserServiceInterface;

class UserService implements UserServiceInterface {

    /**
     * returns confirming the module works
     *
     * @param string $module
     * @return string
     */
    public function getUserSample(string $module): string {
        return "this is user module requested from $module module";
    }
}
