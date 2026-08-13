<?php

namespace Database\Seeders\Academic;

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class ProgramSeeder extends Seeder {

    /**
     * Seed degree programs. Department and DEGREE_LEVEL FKs both resolve by
     * stable code — never by an auto-increment id.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('ProgramSeeder cannot proceed: no user found.');
            return;
        }

        $departmentByCode = fn (string $code): ?int => Department::query()->where('code', $code)->value('id');
        $degreeLevelByCode = fn (string $code): ?int => LookupService::getValueByCode(DEGREE_LEVEL, $code, needId: true);

        if (!$departmentByCode('CS')) {
            consoleError('ProgramSeeder cannot proceed: run DepartmentSeeder first.');
            return;
        }

        if (!$degreeLevelByCode(DEGREE_LEVEL_BACHELOR)) {
            consoleError('ProgramSeeder cannot proceed: DEGREE_LEVEL lookup values are missing.');
            return;
        }

        $programs = [
            ['code' => 'BSC-CS', 'department_code' => 'CS', 'degree_level' => DEGREE_LEVEL_BACHELOR, 'study_mode' => STUDY_MODE_REGULAR, 'duration_years' => 4, 'name' => ['en' => 'BSc in Computer Science', 'am' => 'የኮምፒውተር ሳይንስ ባችለር']],
            ['code' => 'MSC-CS', 'department_code' => 'CS', 'degree_level' => DEGREE_LEVEL_MASTER, 'study_mode' => STUDY_MODE_EXTENSION, 'duration_years' => 2, 'name' => ['en' => 'MSc in Computer Science', 'am' => 'የኮምፒውተር ሳይንስ ማስተርስ']],
            ['code' => 'BSC-SE', 'department_code' => 'SE', 'degree_level' => DEGREE_LEVEL_BACHELOR, 'study_mode' => STUDY_MODE_REGULAR, 'duration_years' => 5, 'name' => ['en' => 'BSc in Software Engineering', 'am' => 'የሶፍትዌር ምህንድስና ባችለር']],
            ['code' => 'BSC-EE', 'department_code' => 'EE', 'degree_level' => DEGREE_LEVEL_BACHELOR, 'study_mode' => STUDY_MODE_EVENING, 'duration_years' => 5, 'name' => ['en' => 'BSc in Electrical Engineering', 'am' => 'የኤሌክትሪካል ምህንድስና ባችለር']],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($programs as $program) {
                $row = Program::firstOrNew(['code' => $program['code']]);
                $row->fill([
                    'name' => [
                        English::getKey() => $program['name']['en'],
                        Amharic::getKey() => $program['name']['am'],
                    ],
                    'department_id' => $departmentByCode($program['department_code']),
                    'degree_level_lookup_value_id' => $degreeLevelByCode($program['degree_level']),
                    // Which grid the generator places this programme into.
                    'study_mode_lookup_value_id' => LookupService::getValueByCode(STUDY_MODE, $program['study_mode'], needId: true),
                    'duration_years' => $program['duration_years'],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed programs: ' . $exception->getMessage());
        }
    }
}
