<?php

namespace Helper\Type\ScheduleType;

use App\Constants\ScheduleConstant;

class ScheduleExam extends ScheduleType {
    public static $id = ScheduleConstant::TYPE_EXAM;
    public static $name = 'schedule_type_exam';
}
