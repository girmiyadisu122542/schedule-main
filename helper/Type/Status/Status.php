<?php

namespace Helper\Type\Status;

use Helper\Type\Type;

class Status extends Type {
    public static $id;
    public static $name;

    public const TYPES = [Pending::class, Approved::class, Rejected::class];
}