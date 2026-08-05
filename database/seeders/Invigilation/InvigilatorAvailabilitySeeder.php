<?php

namespace Database\Seeders\Invigilation;

use App\Constants\ScheduleConstant;
use App\Models\Academic\Semester;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Models\People\Instructor;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvigilatorAvailabilitySeeder extends Seeder {

    /**
     * Offer every invigilator-capable instructor both daily windows across the
     * exam period, so step 15's auto-assignment has a real pool to draw from.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('InvigilatorAvailabilitySeeder cannot proceed: no user found.');
            return;
        }

        $semester = Semester::query()->where('is_current', true)->first();
        if (!$semester) {
            consoleError('InvigilatorAvailabilitySeeder cannot proceed: run SemesterSeeder first.');
            return;
        }

        $instructors = Instructor::query()
            ->where('can_invigilate', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($instructors->isEmpty()) {
            consoleError('InvigilatorAvailabilitySeeder cannot proceed: run InstructorSeeder first.');
            return;
        }

        // The same stretch the exam generator places sittings in.
        $end = Carbon::parse($semester->end_date);
        $start = $end->copy()->subDays(ScheduleConstant::EXAM_PERIOD_DAYS);
        if ($start->lessThan(Carbon::parse($semester->start_date))) {
            $start = Carbon::parse($semester->start_date);
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($instructors as $instructor) {
                for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
                    if ($date->dayOfWeekIso === ScheduleConstant::DAY_SUNDAY) {
                        continue;
                    }

                    foreach (ScheduleConstant::EXAM_TIME_SLOTS as $slot) {
                        // The composite unique is the natural key here, so
                        // firstOrNew on it keeps re-seeding idempotent.
                        $row = InvigilatorAvailability::firstOrNew([
                            'instructor_id' => $instructor->id,
                            'available_date' => $date->toDateString(),
                            'start_time' => $slot['start'],
                            'end_time' => $slot['end'],
                        ]);
                        $row->fill([
                            'semester_id' => $semester->id,
                            'submitted_by_id' => $user->id,
                        ]);
                        $row->save();
                    }
                }
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed invigilator availabilities: ' . $exception->getMessage());
        }
    }
}
