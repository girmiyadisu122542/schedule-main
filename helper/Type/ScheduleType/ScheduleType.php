<?php

namespace Helper\Type\ScheduleType;

use Helper\Type\Type;

class ScheduleType extends Type {
    public static $id;
    public static $name;

    public const TYPES = [ScheduleClass::class, ScheduleExam::class];
}
