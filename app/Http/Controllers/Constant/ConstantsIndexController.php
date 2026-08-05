<?php

namespace App\Http\Controllers\Constant;

use App\Constants\ScheduleConstant;
use App\Http\Controllers\Controller;
use Helper\Response\Response;
use Helper\Type\DayOfWeek\DayOfWeek;
use Helper\Type\Gender\Gender;

class ConstantsIndexController extends Controller {
    /**
     * Gender constants used by the frontend forms.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGender() {
        $data = [
            'gender' => Gender::idAndName(),
        ];

        return Response::_200($data);
    }

    /**
     * Scheduling constants the timetable screens need: the seven weekdays with
     * their translated names, the days generation actually places classes on,
     * and the daily slot grid.
     *
     * `day_of_week` is a plain smallint, not a lookup — the calendar is not a
     * vocabulary a university edits — so the frontend gets its labels from here
     * rather than from `/lookup`.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScheduling() {
        $data = [
            'days_of_week' => DayOfWeek::idAndName(),
            'teaching_days' => ScheduleConstant::TEACHING_DAYS,
            'time_slots' => ScheduleConstant::GENERATION_TIME_SLOTS,
        ];

        return Response::_200($data);
    }
}
