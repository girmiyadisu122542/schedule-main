<?php

namespace App\Services\Report;

use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use App\Services\Lookup\LookupService;
use App\Services\Schedule\InstructorWorkloadService;
use Illuminate\Support\Collection;

/**
 * The three reports a registrar actually runs, plus the exceptions list.
 *
 * Deliberately three fixed reports rather than a report builder. A builder is
 * the classic over-build here: it takes ten times as long, and the questions an
 * examinations office asks are always the same three — is a room being used, is
 * an instructor overloaded, and what is still broken about this term.
 *
 * Every figure is an aggregate over tables that already exist, and nothing is
 * cached: these are read a few times a week, not a few times a second, and a
 * stale utilisation figure is worse than a slow one.
 */
class ScheduleReportService {

    /** Minutes in an hour — schedules store times, reports show hours. */
    private const MINUTES_PER_HOUR = 60;

    /**
     * Room utilisation for one semester (C32).
     *
     * Two different numbers, because they answer two different questions:
     * `hours_per_week` is how much of the week the room is booked, and
     * `seat_occupancy` is how full it is while booked. A room can be busy all
     * week and still be the wrong room, if it seats 300 for classes of 40.
     *
     * @param int $semesterId
     * @param array<string, mixed> $filters building_id, campus_id
     *
     * @return array
     */
    public function roomUtilisation(int $semesterId, array $filters = []): array {
        $rooms = Room::query()
            ->with('building.campus')
            ->where('is_active', true)
            ->when($filters['building_id'] ?? null, fn ($query, $id) => $query->where('building_id', (int) $id))
            ->when($filters['campus_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'building',
                fn ($building) => $building->where('campus_id', (int) $id),
            ))
            ->get();

        $byRoom = ClassSchedule::query()
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            ->whereNotNull('room_id')
            ->with('courseOffering')
            ->get()
            ->groupBy('room_id');

        $rows = $rooms->map(function (Room $room) use ($byRoom) {
            $sessions = $byRoom->get($room->id, collect());
            $minutes = $sessions->sum(fn ($session) => $this->minutesBetween($session->start_time, $session->end_time));
            $capacity = max(1, (int) $room->capacity);

            // Averaged over the sessions actually held rather than over the
            // whole week: a room used once at full capacity is fully occupied
            // when in use, and that is the honest reading of the number.
            $occupancy = $sessions->isEmpty()
                ? 0.0
                : round($sessions->avg(fn ($session) => min(100, ((int) $session->courseOffering?->expected_students / $capacity) * 100)), 1);

            return [
                'room_id' => $room->id,
                'room_code' => $room->code,
                'room_name' => $room->name__localized,
                'building' => $room->building?->name__localized,
                'campus' => $room->building?->campus?->name__localized,
                'capacity' => (int) $room->capacity,
                'exam_capacity' => $room->exam_capacity ? (int) $room->exam_capacity : null,
                'session_count' => $sessions->count(),
                'hours_per_week' => round($minutes / self::MINUTES_PER_HOUR, 1),
                'seat_occupancy' => $occupancy,
            ];
        });

        return [
            // Busiest first: the rooms under pressure are what a capacity
            // decision is actually about.
            'rows' => $rows->sortByDesc('hours_per_week')->values()->all(),
            'totals' => [
                'room_count' => $rooms->count(),
                'rooms_in_use' => $rows->where('session_count', '>', 0)->count(),
                'rooms_unused' => $rows->where('session_count', 0)->count(),
                'total_hours' => round($rows->sum('hours_per_week'), 1),
            ],
        ];
    }

    /**
     * Instructor workload against the declared ceiling (C33).
     *
     * Invigilation duties are counted alongside teaching, because an
     * examinations office that staffs exams from the same people who teach
     * needs both in one place to see who is actually loaded.
     *
     * @param int $semesterId
     * @param array<string, mixed> $filters department_id
     *
     * @return array
     */
    public function instructorWorkload(int $semesterId, array $filters = []): array {
        $instructors = Instructor::query()
            ->with('department')
            ->where('is_active', true)
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', (int) $id))
            ->get();

        $workload = app(InstructorWorkloadService::class);

        $rows = $instructors->map(function (Instructor $instructor) use ($semesterId, $workload) {
            $teachingMinutes = $workload->committedMinutes($instructor->id, $semesterId);
            $limitMinutes = $workload->limitMinutes($instructor->id);

            $duties = ExamSchedule::query()
                ->where('semester_id', $semesterId)
                ->whereHas('examInvigilatorAssignments', fn ($duty) => $duty
                    ->where('instructor_id', $instructor->id)
                    ->where('state', STATE_ACTIVE))
                ->count();

            return [
                'instructor_id' => $instructor->id,
                'employee_no' => $instructor->employee_no,
                'name' => $instructor->full_name__localized,
                'department' => $instructor->department?->name__localized,
                'teaching_hours' => round($teachingMinutes / self::MINUTES_PER_HOUR, 1),
                'max_weekly_hours' => $limitMinutes === null ? null : round($limitMinutes / self::MINUTES_PER_HOUR, 1),
                // Null means no ceiling was set, which is not the same as
                // nought per cent used — the UI has to tell them apart.
                'utilisation' => $limitMinutes ? round(($teachingMinutes / $limitMinutes) * 100, 1) : null,
                'is_over_limit' => $limitMinutes !== null && $teachingMinutes > $limitMinutes,
                'invigilation_duties' => $duties,
            ];
        });

        return [
            'rows' => $rows->sortByDesc('teaching_hours')->values()->all(),
            'totals' => [
                'instructor_count' => $instructors->count(),
                'over_limit' => $rows->where('is_over_limit', true)->count(),
                'no_limit_set' => $rows->whereNull('max_weekly_hours')->count(),
                'unassigned' => $rows->where('teaching_hours', 0.0)->count(),
            ],
        ];
    }

    /**
     * Everything wrong with this term, in one list (C23, C31).
     *
     * The registrar's morning screen. Each group is one query against tables
     * that already exist — no new bookkeeping, so it cannot drift out of step
     * with the timetable it describes.
     *
     * @param int $semesterId
     * @return array
     */
    public function exceptions(int $semesterId): array {
        $approvedId = LookupService::getValueByCode(
            COURSE_OFFERING_STATUS,
            COURSE_OFFERING_STATUS_REGISTRAR_APPROVED,
            needId: true,
        );

        $unscheduled = CourseOffering::query()
            ->with(['course', 'section', 'department'])
            ->where('semester_id', $semesterId)
            ->when($approvedId, fn ($query) => $query->where('status_lookup_value_id', $approvedId))
            ->whereDoesntHave('classSchedules', fn ($schedule) => $schedule->where('state', STATE_ACTIVE))
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'label' => $offering->displayLabel(),
                'detail' => $offering->department?->name__localized,
            ]);

        $noExam = CourseOffering::query()
            ->with(['course', 'section'])
            ->where('semester_id', $semesterId)
            ->when($approvedId, fn ($query) => $query->where('status_lookup_value_id', $approvedId))
            ->whereDoesntHave('examSchedules', fn ($exam) => $exam->where('state', STATE_ACTIVE))
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'label' => $offering->displayLabel(),
                'detail' => null,
            ]);

        $roomless = ClassSchedule::query()
            ->with(['courseOffering.course'])
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            ->whereNull('room_id')
            ->get()
            ->map(fn (ClassSchedule $session) => [
                'id' => $session->id,
                'label' => $session->courseOffering?->displayLabel(),
                'detail' => $session->timeRange(),
            ]);

        $understaffed = ExamSchedule::query()
            ->with(['courseOffering.course', 'room'])
            ->withCount(['examInvigilatorAssignments as on_duty' => fn ($duty) => $duty->where('state', STATE_ACTIVE)])
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            ->get()
            ->filter(fn (ExamSchedule $exam) => $exam->on_duty < (int) $exam->required_invigilators)
            ->map(fn (ExamSchedule $exam) => [
                'id' => $exam->id,
                'label' => $exam->displayLabel(),
                'detail' => $exam->on_duty . ' of ' . (int) $exam->required_invigilators . ' on duty',
            ])
            ->values();

        // A course several cohorts sit is the one place a student taking it
        // outside their own cohort can be double-booked with nothing catching
        // it. Naming them for review is the honest mitigation while there is
        // no student entity (C19).
        $clashRisk = collect($this->multiSectionCourses($semesterId))->map(fn (array $row) => [
            'id' => $row['course_id'],
            'label' => $row['course_code'] . ' — ' . $row['course_title'],
            'detail' => $row['section_count'] . ' sections',
        ]);

        // Headcounts drift during add/drop, and the section import updates them
        // (C44) — but a room that fitted in week one may not fit in week three,
        // and nothing re-checked it. This is what makes a stale headcount
        // visible instead of leaving a cohort standing in the corridor.
        $outgrown = ClassSchedule::query()
            ->with(['courseOffering.course', 'room'])
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            ->whereNotNull('room_id')
            ->get()
            ->filter(function (ClassSchedule $session): bool {
                $seats = (int) ($session->room?->capacity ?? 0);
                $students = (int) ($session->courseOffering?->expected_students ?? 0);

                return $seats > 0 && $students > $seats;
            })
            ->map(fn (ClassSchedule $session) => [
                'id' => $session->id,
                'label' => $session->courseOffering?->displayLabel(),
                'detail' => ($session->courseOffering?->expected_students ?? 0)
                    . ' in a room seating ' . ($session->room?->capacity ?? 0),
            ])
            ->values();

        $groups = [
            'unscheduled_offerings' => $unscheduled->values()->all(),
            'sessions_over_capacity' => $outgrown->all(),
            'offerings_without_exam' => $noExam->values()->all(),
            'sessions_without_room' => $roomless->values()->all(),
            'exams_short_of_invigilators' => $understaffed->all(),
            'clash_risk_courses' => $clashRisk->values()->all(),
        ];

        return [
            'groups' => $groups,
            'total' => array_sum(array_map('count', $groups)),
        ];
    }

    /**
     * Courses offered to more than one cohort this semester (C19).
     *
     * @param int $semesterId
     * @return array
     */
    public function multiSectionCourses(int $semesterId): array {
        return CourseOffering::query()
            ->with('course')
            ->where('semester_id', $semesterId)
            ->get()
            ->groupBy('course_id')
            ->filter(fn (Collection $group) => $group->pluck('section_id')->filter()->unique()->count() > 1)
            ->map(fn (Collection $group) => [
                'course_id' => (int) $group->first()->course_id,
                'course_code' => $group->first()->course?->code,
                'course_title' => $group->first()->course?->title__localized,
                'section_count' => $group->pluck('section_id')->filter()->unique()->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * The same headline figures for two semesters, side by side (C34).
     *
     * @param int $semesterId
     * @param int $compareSemesterId
     *
     * @return array
     */
    public function compare(int $semesterId, int $compareSemesterId): array {
        return [
            'current' => $this->headline($semesterId),
            'previous' => $this->headline($compareSemesterId),
        ];
    }

    /**
     * One semester in a handful of numbers.
     *
     * @param int $semesterId
     * @return array
     */
    private function headline(int $semesterId): array {
        $rooms = $this->roomUtilisation($semesterId);
        $staff = $this->instructorWorkload($semesterId);

        return [
            'semester_id' => $semesterId,
            'sessions' => ClassSchedule::where('semester_id', $semesterId)->where('state', STATE_ACTIVE)->count(),
            'exams' => ExamSchedule::where('semester_id', $semesterId)->where('state', STATE_ACTIVE)->count(),
            'rooms_in_use' => $rooms['totals']['rooms_in_use'],
            'total_room_hours' => $rooms['totals']['total_hours'],
            'instructors_over_limit' => $staff['totals']['over_limit'],
        ];
    }

    /**
     * Whole minutes between two `HH:MM:SS` times.
     *
     * @param string|null $start
     * @param string|null $end
     *
     * @return int
     */
    private function minutesBetween(?string $start, ?string $end): int {
        if (!$start || !$end) {
            return 0;
        }

        return (int) max(0, (strtotime($end) - strtotime($start)) / 60);
    }
}
