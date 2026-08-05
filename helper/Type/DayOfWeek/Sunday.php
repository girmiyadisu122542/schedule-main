<?php

namespace Helper\Type\DayOfWeek;

use App\Constants\ScheduleConstant;

class Sunday extends DayOfWeek {
    public static $id = ScheduleConstant::DAY_SUNDAY;
    public static $name = 'sunday';
}
