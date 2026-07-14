<?php

namespace Common\Contracts;

interface UserServiceInterface {

    public function getUserSample(string $module): string;
}