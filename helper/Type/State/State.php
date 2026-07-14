<?php

namespace Helper\Type\State;

use Helper\Type\Type;

class State extends Type {
    public static $id;
    public static $name;

    public const TYPES = [StateActive::class, StateInactive::class];
}
