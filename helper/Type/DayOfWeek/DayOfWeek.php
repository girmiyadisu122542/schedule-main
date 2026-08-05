<?php

namespace Helper\Type\DayOfWeek;

use Helper\Type\Type;

/**
 * The seven ISO-8601 weekdays a class meeting can fall on.
 *
 * `class_schedules.day_of_week` is a plain smallint with a CHECK, not a lookup —
 * the calendar is not a vocabulary a university edits. This Type class is what
 * turns 1 into a translated "Monday", both in `resource()` payloads and in the
 * `/constants/scheduling` catalogue the frontend hydrates from.
 */
class DayOfWeek extends Type {
    public static $id;
    public static $name;

    public const TYPES = [
        Monday::class,
        Tuesday::class,
        Wednesday::class,
        Thursday::class,
        Friday::class,
        Saturday::class,
        Sunday::class,
    ];
}
