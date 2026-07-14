<?php

namespace App\Constants;

class ScheduleConstant {
    /** Schedule entry kinds */
    public const TYPE_CLASS = 1;
    public const TYPE_EXAM = 2;

    /** Days of week (ISO-8601: 1 = Monday ... 7 = Sunday) */
    public const DAY_MONDAY = 1;
    public const DAY_SUNDAY = 7;

    public const TIME_FORMAT = 'H:i';
}
