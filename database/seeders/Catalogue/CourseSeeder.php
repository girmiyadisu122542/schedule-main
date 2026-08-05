<?php

namespace Database\Seeders\Catalogue;

use App\Models\Academic\Department;
use App\Models\Catalogue\Course;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class CourseSeeder extends Seeder {

    /**
     * Seed the reusable course catalogue. Department and COURSE_TYPE FKs both
     * resolve by stable code. The weekly-load columns are filled because the
     * class generator (step 11) fans them out into meetings.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('CourseSeeder cannot proceed: no user found.');
            return;
        }

        $departmentByCode = fn (string $code): ?int => Department::query()->where('code', $code)->value('id');
        $courseTypeByCode = fn (string $code): ?int => LookupService::getValueByCode(COURSE_TYPE, $code, needId: true);

        if (!$departmentByCode('CS')) {
            consoleError('CourseSeeder cannot proceed: run DepartmentSeeder first.');
            return;
        }

        if (!$courseTypeByCode(COURSE_TYPE_LECTURE)) {
            consoleError('CourseSeeder cannot proceed: COURSE_TYPE lookup values are missing.');
            return;
        }

        $courses = [
            ['code' => 'CS101', 'department' => 'CS', 'type' => COURSE_TYPE_LECTURE_LAB, 'credit' => 4, 'contact' => 6, 'lecture' => 3, 'lab' => 3, 'tutorial' => null, 'sessions' => 2, 'title' => ['en' => 'Introduction to Computer Science', 'am' => 'የኮምፒውተር ሳይንስ መግቢያ']],
            ['code' => 'CS201', 'department' => 'CS', 'type' => COURSE_TYPE_LECTURE_LAB, 'credit' => 4, 'contact' => 6, 'lecture' => 3, 'lab' => 3, 'tutorial' => null, 'sessions' => 2, 'title' => ['en' => 'Data Structures and Algorithms', 'am' => 'የውሂብ አወቃቀሮች እና አልጎሪዝሞች']],
            ['code' => 'CS210', 'department' => 'CS', 'type' => COURSE_TYPE_LECTURE, 'credit' => 3, 'contact' => 3, 'lecture' => 3, 'lab' => null, 'tutorial' => 1, 'sessions' => 2, 'title' => ['en' => 'Discrete Mathematics', 'am' => 'ልዩ ሒሳብ']],
            ['code' => 'SE301', 'department' => 'SE', 'type' => COURSE_TYPE_LECTURE_LAB, 'credit' => 4, 'contact' => 6, 'lecture' => 3, 'lab' => 3, 'tutorial' => null, 'sessions' => 2, 'title' => ['en' => 'Software Engineering Principles', 'am' => 'የሶፍትዌር ምህንድስና መርሆዎች']],
            ['code' => 'EE201', 'department' => 'EE', 'type' => COURSE_TYPE_LECTURE_LAB, 'credit' => 4, 'contact' => 6, 'lecture' => 3, 'lab' => 3, 'tutorial' => null, 'sessions' => 2, 'title' => ['en' => 'Circuit Analysis', 'am' => 'የወረዳ ትንተና']],
            ['code' => 'MGT101', 'department' => 'MGT', 'type' => COURSE_TYPE_LECTURE, 'credit' => 3, 'contact' => 3, 'lecture' => 3, 'lab' => null, 'tutorial' => null, 'sessions' => 1, 'title' => ['en' => 'Principles of Management', 'am' => 'የአስተዳደር መርሆዎች']],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($courses as $course) {
                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = Course::firstOrNew(['code' => $course['code']]);
                $row->fill([
                    'title' => [
                        English::getKey() => $course['title']['en'],
                        Amharic::getKey() => $course['title']['am'],
                    ],
                    'department_id' => $departmentByCode($course['department']),
                    'course_type_lookup_value_id' => $courseTypeByCode($course['type']),
                    'credit_hours' => $course['credit'],
                    'contact_hours' => $course['contact'],
                    'lecture_hours_per_week' => $course['lecture'],
                    'lab_hours_per_week' => $course['lab'],
                    'tutorial_hours_per_week' => $course['tutorial'],
                    'sessions_per_week' => $course['sessions'],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed courses: ' . $exception->getMessage());
        }
    }
}
