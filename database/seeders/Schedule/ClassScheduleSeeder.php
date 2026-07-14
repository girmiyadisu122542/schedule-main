<?php

namespace Database\Seeders\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Schedule\ClassSchedule;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\English;

class ClassScheduleSeeder extends Seeder {

    /**
     * Seed a handful of sample class and exam schedule rows for the
     * starter kit. Owned by the first (admin) user.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('ClassScheduleSeeder cannot proceed: no user found.');
            return;
        }

        $schedules = [
            [
                'name' => 'Mathematics 101',
                'schedule_type' => ScheduleConstant::TYPE_CLASS,
                'day_of_week' => ScheduleConstant::DAY_MONDAY,
                'start_time' => '08:00',
                'end_time' => '09:30',
                'room' => 'A-101',
                'section' => 'Grade 9 - A',
            ],
            [
                'name' => 'Physics 101',
                'schedule_type' => ScheduleConstant::TYPE_CLASS,
                'day_of_week' => 3, // Wednesday
                'start_time' => '10:00',
                'end_time' => '11:30',
                'room' => 'B-204',
                'section' => 'Grade 9 - A',
            ],
            [
                'name' => 'Mathematics Midterm Exam',
                'schedule_type' => ScheduleConstant::TYPE_EXAM,
                'exam_date' => '2026-08-15',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'room' => 'Exam Hall 1',
                'section' => 'Grade 9 - A',
            ],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($schedules as $schedule) {
                ClassSchedule::updateOrCreate(
                    ['code' => Str::slug($schedule['name'])],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => [English::getKey() => $schedule['name']],
                        'schedule_type' => $schedule['schedule_type'],
                        'day_of_week' => $schedule['day_of_week'] ?? null,
                        'exam_date' => $schedule['exam_date'] ?? null,
                        'start_time' => $schedule['start_time'],
                        'end_time' => $schedule['end_time'],
                        'room' => $schedule['room'],
                        'section' => $schedule['section'],
                        'state' => STATE_ACTIVE,
                        'user_id' => $user->id,
                    ]
                );
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            echo "Unable to seed class schedules: " . $exception->getMessage();
        }
    }
}
