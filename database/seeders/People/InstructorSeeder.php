<?php

namespace Database\Seeders\People;

use App\Models\Academic\Department;
use App\Models\People\Instructor;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class InstructorSeeder extends Seeder {

    /**
     * Seed the instructor registry. One row links to the seeded teacher account
     * to exercise the optional person FK; the rest have no portal account, which
     * is the normal case for a registry that precedes logins.
     *
     * A lab technician who only invigilates and a visiting lecturer exempt from
     * duty are both seeded, so the capability flags have real data behind them.
     *
     * @return void
     */
    public function run(): void {
        $departmentByCode = fn (string $code): ?int => Department::query()->where('code', $code)->value('id');
        $academicRankByCode = fn (string $code): ?int => LookupService::getValueByCode(ACADEMIC_RANK, $code, needId: true);

        if (!$departmentByCode('CS')) {
            consoleError('InstructorSeeder cannot proceed: run DepartmentSeeder first.');
            return;
        }

        if (!$academicRankByCode(ACADEMIC_RANK_LECTURER)) {
            consoleError('InstructorSeeder cannot proceed: ACADEMIC_RANK lookup values are missing.');
            return;
        }

        $teacherAccountId = User::query()->where('email', 'teacher@schedule.com')->value('id');

        $instructors = [
            ['employee_no' => 'EMP-1001', 'department' => 'CS', 'rank' => ACADEMIC_RANK_ASSISTANT_PROFESSOR, 'can_teach' => true, 'can_invigilate' => true, 'max_weekly_hours' => 18, 'user_id' => $teacherAccountId, 'name' => ['en' => 'Dr. Alemu Bekele', 'am' => 'ዶ/ር አለሙ በቀለ']],
            ['employee_no' => 'EMP-1002', 'department' => 'CS', 'rank' => ACADEMIC_RANK_LECTURER, 'can_teach' => true, 'can_invigilate' => true, 'max_weekly_hours' => 20, 'user_id' => null, 'name' => ['en' => 'Hanna Girma', 'am' => 'ሃና ግርማ']],
            ['employee_no' => 'EMP-1003', 'department' => 'SE', 'rank' => ACADEMIC_RANK_LECTURER, 'can_teach' => true, 'can_invigilate' => true, 'max_weekly_hours' => 20, 'user_id' => null, 'name' => ['en' => 'Yonas Tesfaye', 'am' => 'ዮናስ ተስፋዬ']],
            ['employee_no' => 'EMP-1004', 'department' => 'EE', 'rank' => ACADEMIC_RANK_ASSOCIATE_PROFESSOR, 'can_teach' => true, 'can_invigilate' => false, 'max_weekly_hours' => 12, 'user_id' => null, 'name' => ['en' => 'Prof. Meaza Tadesse', 'am' => 'ፕ/ር መአዛ ታደሰ']],
            // Only invigilates — the reason one table serves both populations.
            ['employee_no' => 'EMP-1005', 'department' => 'CS', 'rank' => ACADEMIC_RANK_TECHNICAL_ASSISTANT, 'can_teach' => false, 'can_invigilate' => true, 'max_weekly_hours' => null, 'user_id' => null, 'name' => ['en' => 'Samuel Kebede', 'am' => 'ሳሙኤል ከበደ']],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($instructors as $instructor) {
                $row = Instructor::firstOrNew(['employee_no' => $instructor['employee_no']]);
                $row->fill([
                    'full_name' => [
                        English::getKey() => $instructor['name']['en'],
                        Amharic::getKey() => $instructor['name']['am'],
                    ],
                    'department_id' => $departmentByCode($instructor['department']),
                    'academic_rank_lookup_value_id' => $academicRankByCode($instructor['rank']),
                    'user_id' => $instructor['user_id'],
                    'can_teach' => $instructor['can_teach'],
                    'can_invigilate' => $instructor['can_invigilate'],
                    'max_weekly_hours' => $instructor['max_weekly_hours'],
                    'is_active' => true,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed instructors: ' . $exception->getMessage());
        }
    }
}
