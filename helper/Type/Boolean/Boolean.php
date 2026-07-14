<?php

namespace Helper\Type\Boolean;

use Helper\Type\Type;

class Boolean extends Type {
    public static $id;
    public static $name;

    public const TYPES = [BooleanTrue::class, BooleanFalse::class];
}