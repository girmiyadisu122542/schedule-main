<?php

namespace Helper\Type\Gender;

use Helper\Type\Type;

class Gender extends Type {
    public static $id;
    public static $name;

    public const TYPES = [Male::class, Female::class];
}