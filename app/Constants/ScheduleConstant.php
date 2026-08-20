<?php

namespace App\Constants;

class ScheduleConstant {
    /** Schedule entry kinds */
    public const TYPE_CLASS = 1;
    public const TYPE_EXAM = 2;

    /** Days of week (ISO-8601: 1 = Monday ... 7 = Sunday) */
    public const DAY_MONDAY = 1;
    public const DAY_TUESDAY = 2;
    public const DAY_WEDNESDAY = 3;
    public const DAY_THURSDAY = 4;
    public const DAY_FRIDAY = 5;
    public const DAY_SATURDAY = 6;
    public const DAY_SUNDAY = 7;

    public const TIME_FORMAT = 'H:i';

    /**
     * The days the class generator places meetings on. Weekends are left out:
     * a Saturday meeting is legal (the CHECK allows 1..7, and a registrar may
     * place one by hand), it is simply not somewhere automatic generation puts
     * a class unasked.
     *
     * @var array<int, int>
     */
    public const TEACHING_DAYS = [
        self::DAY_MONDAY,
        self::DAY_TUESDAY,
        self::DAY_WEDNESDAY,
        self::DAY_THURSDAY,
        self::DAY_FRIDAY,
    ];

    /**
     * The daily grid the generator places meetings into — 90-minute teaching
     * slots either side of a lunch break. Every generated meeting starts on one
     * of these boundaries, which is what makes a produced timetable readable.
     *
     * @var array<int, array<string, string>>
     */
    public const GENERATION_TIME_SLOTS = [
        ['start' => '08:00', 'end' => '09:30'],
        ['start' => '09:45', 'end' => '11:15'],
        ['start' => '11:30', 'end' => '13:00'],
        ['start' => '14:00', 'end' => '15:30'],
        ['start' => '15:45', 'end' => '17:15'],
    ];

    /**
     * Meetings per offering when the course declares no `sessions_per_week`,
     * and the ceiling that stops a mis-entered course from consuming the whole
     * grid.
     */
    public const DEFAULT_SESSIONS_PER_WEEK = 2;
    public const MAX_SESSIONS_PER_WEEK = 5;

    /**
     * The lifecycle decisions a bulk run may carry.
     *
     * `delete` is here alongside `cancel` because they are different acts:
     * cancelling keeps the row and frees its slot, deleting removes a draft
     * that should never have existed. Both are offered; neither is implied.
     *
     * @var array<int, string>
     */
    public const BULK_ACTIONS = ['publish', 'confirm', 'cancel', 'delete'];

    /**
     * How many rows one bulk run may carry.
     *
     * A run is a loop of real service calls, each with its own transaction and
     * locking reads — not a single UPDATE — so the ceiling is what keeps one
     * request from running past any sensible timeout.
     */
    public const MAX_BULK_ROWS = 200;

    /**
     * The sittings-per-day grid for exam scheduling. Fewer, longer windows than
     * the teaching grid: an exam session runs three hours and a hall needs
     * turning round between them.
     *
     * @var array<int, array<string, string>>
     */
    public const EXAM_TIME_SLOTS = [
        ['start' => '09:00', 'end' => '12:00'],
        ['start' => '14:00', 'end' => '17:00'],
    ];

    /**
     * The exam period sits at the end of the semester. Generation walks forward
     * from `end_date - EXAM_PERIOD_DAYS`, skipping Sundays.
     */
    public const EXAM_PERIOD_DAYS = 14;

    /** How many invigilators a generated sitting asks for by default. */
    public const DEFAULT_REQUIRED_INVIGILATORS = 2;

    /** Minutes in an hour — settings store hours, the engine works in minutes. */
    public const MINUTES_PER_HOUR = 60;

    /**
     * Fallbacks for the rules `schedule_settings` configures, used only when
     * nothing has been configured at all. Each one matches the column default,
     * so an unseeded database behaves like a seeded one.
     */
    public const DEFAULT_MAX_EXAMS_PER_DAY = 2;

    public const DEFAULT_STUDENTS_PER_INVIGILATOR = 50;

    /** Soft-constraint weights. Zero switches a preference off entirely. */
    public const DEFAULT_WEIGHT_SPREAD = 10;

    public const DEFAULT_WEIGHT_GAPS = 6;

    public const DEFAULT_WEIGHT_ROOM_FIT = 3;

    public const DEFAULT_WEIGHT_BUILDING = 4;
}
