<?php

namespace Database\Seeders\Academic;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class DepartmentSeeder extends Seeder {

    /**
     * Seed departments under the seeded colleges. College FKs resolve by the
     * stable college `code`, never an auto-increment id.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('DepartmentSeeder cannot proceed: no user found.');
            return;
        }

        $collegeByCode = fn (string $code): ?int => College::query()->where('code', $code)->value('id');

        if (!$collegeByCode('COET')) {
            consoleError('DepartmentSeeder cannot proceed: run CollegeSeeder first.');
            return;
        }

        $departments = [
            ['code' => 'CS', 'college_code' => 'CNCS', 'name' => ['en' => 'Computer Science', 'am' => 'ኮምፒውተር ሳይንስ']],
            ['code' => 'SE', 'college_code' => 'COET', 'name' => ['en' => 'Software Engineering', 'am' => 'ሶፍትዌር ምህንድስና']],
            ['code' => 'EE', 'college_code' => 'COET', 'name' => ['en' => 'Electrical Engineering', 'am' => 'ኤሌክትሪካል ምህንድስና']],
            ['code' => 'MGT', 'college_code' => 'CBE', 'name' => ['en' => 'Management', 'am' => 'ማኔጅመንት']],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($departments as $department) {
                $row = Department::firstOrNew(['code' => $department['code']]);
                $row->fill([
                    'name' => [
                        English::getKey() => $department['name']['en'],
                        Amharic::getKey() => $department['name']['am'],
                    ],
                    'college_id' => $collegeByCode($department['college_code']),
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed departments: ' . $exception->getMessage());
        }
    }
}
