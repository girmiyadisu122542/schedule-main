<?php

namespace Database\Seeders\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClassScheduleSeeder extends Seeder {

    /**
     * Seed a small draft timetable for the current semester, so the scheduling
     * screens and the generator both have something real to work against.
     *
     * This deliberately does NOT call the generator service: a seeder has no
     * authenticated user, and `created_by_id` is not nullable. It places the
     * same shape of rows by hand, off the same slot grid.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('ClassScheduleSeeder cannot proceed: no user found.');
            return;
        }

        $semester = Semester::query()->where('is_current', true)->first();
        if (!$semester) {
            consoleError('ClassScheduleSeeder cannot proceed: run SemesterSeeder first.');
            return;
        }

        $draftStatusId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_DRAFT, needId: true);
        $publishedStatusId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_PUBLISHED, needId: true);
        $lectureTypeId = LookupService::getValueByCode(SESSION_TYPE, SESSION_TYPE_LECTURE, needId: true);

        if (!$draftStatusId || !$publishedStatusId || !$lectureTypeId) {
            consoleError('ClassScheduleSeeder cannot proceed: CLASS_SCHEDULE_STATUS / SESSION_TYPE lookup values are missing.');
            return;
        }

        $approvedId = LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_REGISTRAR_APPROVED, needId: true);
        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->where('status_lookup_value_id', $approvedId)
            ->orderBy('id')
            ->get();

        if ($offerings->isEmpty()) {
            consoleError('ClassScheduleSeeder cannot proceed: run CourseOfferingSeeder first.');
            return;
        }

        $rooms = Room::query()->where('is_active', true)->orderBy('capacity')->get();
        if ($rooms->isEmpty()) {
            consoleError('ClassScheduleSeeder cannot proceed: run RoomSeeder first.');
            return;
        }

        // A deterministic walk over the grid: offering N starts on day N and
        // slot N, so the seeded timetable is stable across rebuilds.
        //
        // Everything but the LAST offering is published, so the timetable view
        // has real content while the adjustment screen still has a draft to
        // work on.
        $lastOfferingId = $offerings->last()->id;
        $index = 0;
        foreach ($offerings as $offering) {
            $room = $rooms->firstWhere(fn (Room $candidate): bool => $candidate->capacity >= $offering->expected_students) ?? $rooms->last();

            $isDraft = $offering->id === $lastOfferingId;

            for ($session = 0; $session < ScheduleConstant::DEFAULT_SESSIONS_PER_WEEK; $session++) {
                $day = ScheduleConstant::TEACHING_DAYS[($index + $session * 2) % count(ScheduleConstant::TEACHING_DAYS)];
                $slot = ScheduleConstant::GENERATION_TIME_SLOTS[$index % count(ScheduleConstant::GENERATION_TIME_SLOTS)];

                // Each row commits on its own: an EXCLUDE rejection means the
                // slot was taken, and the rest of the timetable is still good.
                try {
                    // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                    $row = ClassSchedule::firstOrNew([
                        'course_offering_id' => $offering->id,
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                    ]);
                    $row->fill([
                        'semester_id' => $offering->semester_id,
                        'section_id' => $offering->section_id,
                        'instructor_id' => $offering->instructor_id,
                        'room_id' => $room->id,
                        'session_type_lookup_value_id' => $lectureTypeId,
                        'end_time' => $slot['end'],
                        'status_lookup_value_id' => $isDraft ? $draftStatusId : $publishedStatusId,
                        'state' => STATE_ACTIVE,
                        'created_by_id' => $user->id,
                        'published_by_id' => $isDraft ? null : $user->id,
                        'published_at' => $isDraft ? null : now(),
                    ]);
                    $row->uuid ??= (string) Str::uuid();
                    $row->save();
                } catch (QueryException $exception) {
                    consoleError('ClassScheduleSeeder skipped a clashing meeting: ' . $exception->getMessage());
                }
            }

            $index++;
        }
    }
}
