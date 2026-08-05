<?php

namespace Database\Seeders\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ExamSchedule;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ExamScheduleSeeder extends Seeder {

    /**
     * Seed one final sitting per registrar-approved offering, so the exam
     * screens and the invigilation roster both have real inputs.
     *
     * This deliberately does NOT call the generator service: a seeder has no
     * authenticated user, and `created_by_id` is not nullable. It places the
     * same shape of rows off the same date and slot grid.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('ExamScheduleSeeder cannot proceed: no user found.');
            return;
        }

        $semester = Semester::query()->where('is_current', true)->first();
        if (!$semester) {
            consoleError('ExamScheduleSeeder cannot proceed: run SemesterSeeder first.');
            return;
        }

        $draftStatusId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_DRAFT, needId: true);
        $publishedStatusId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_PUBLISHED, needId: true);
        $finalTypeId = LookupService::getValueByCode(EXAM_TYPE, EXAM_TYPE_FINAL, needId: true);

        if (!$draftStatusId || !$publishedStatusId || !$finalTypeId) {
            consoleError('ExamScheduleSeeder cannot proceed: EXAM_SCHEDULE_STATUS / EXAM_TYPE lookup values are missing.');
            return;
        }

        $approvedId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_REGISTRAR_APPROVED, needId: true);
        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->where('status_lookup_value_id', $approvedId)
            ->orderBy('id')
            ->get();

        if ($offerings->isEmpty()) {
            consoleError('ExamScheduleSeeder cannot proceed: run CourseOfferingSeeder first.');
            return;
        }

        // Exam venues, judged on spaced-seating capacity.
        $venues = Room::query()
            ->where('is_active', true)
            ->where('is_exam_venue', true)
            ->orderByRaw('COALESCE(exam_capacity, capacity)')
            ->get();

        if ($venues->isEmpty()) {
            consoleError('ExamScheduleSeeder cannot proceed: run RoomSeeder first.');
            return;
        }

        // The same stretch the exam generator places sittings in.
        $end = Carbon::parse($semester->end_date);
        $start = $end->copy()->subDays(ScheduleConstant::EXAM_PERIOD_DAYS);
        if ($start->lessThan(Carbon::parse($semester->start_date))) {
            $start = Carbon::parse($semester->start_date);
        }

        // A deterministic walk: offering N takes date N, so the seeded calendar
        // is stable across rebuilds. Everything but the LAST sitting is
        // published, so the exam calendar has content and the exam screen still
        // has a draft to move through the lifecycle.
        $lastOfferingId = $offerings->last()->id;
        $index = 0;
        foreach ($offerings as $offering) {
            $venue = $venues->first(
                fn (Room $room): bool => ($room->exam_capacity ?? $room->capacity) >= $offering->expected_students
            ) ?? $venues->last();

            $date = $start->copy()->addDays($index);
            if ($date->dayOfWeekIso === ScheduleConstant::DAY_SUNDAY) {
                $date->addDay();
            }

            $slot = ScheduleConstant::EXAM_TIME_SLOTS[$index % count(ScheduleConstant::EXAM_TIME_SLOTS)];
            $isDraft = $offering->id === $lastOfferingId;

            try {
                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = ExamSchedule::firstOrNew([
                    'course_offering_id' => $offering->id,
                    'exam_type_lookup_value_id' => $finalTypeId,
                ]);
                $row->fill([
                    'semester_id' => $offering->semester_id,
                    'section_id' => $offering->section_id,
                    'exam_date' => $date->toDateString(),
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'room_id' => $venue->id,
                    'required_invigilators' => ScheduleConstant::DEFAULT_REQUIRED_INVIGILATORS,
                    'status_lookup_value_id' => $isDraft ? $draftStatusId : $publishedStatusId,
                    'state' => STATE_ACTIVE,
                    'created_by_id' => $user->id,
                    'published_by_id' => $isDraft ? null : $user->id,
                    'published_at' => $isDraft ? null : now(),
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            } catch (QueryException $exception) {
                consoleError('ExamScheduleSeeder skipped a clashing sitting: ' . $exception->getMessage());
            }

            $index++;
        }
    }
}
