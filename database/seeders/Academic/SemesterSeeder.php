<?php

namespace Database\Seeders\Academic;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Semester;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class SemesterSeeder extends Seeder {

    /**
     * Seed the two semesters of the current academic year. Semester 1 is closed
     * and Semester 2 is the current, active one — enough state for the
     * offering/scheduling steps to have something to work against.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('SemesterSeeder cannot proceed: no user found.');
            return;
        }

        $currentYear = AcademicYear::query()->where('is_current', true)->first();
        if (!$currentYear) {
            consoleError('SemesterSeeder cannot proceed: run AcademicYearSeeder first.');
            return;
        }

        $statusByCode = fn (string $code): ?int => LookupService::getValueByCode(SEMESTER_STATUS, $code, needId: true);

        if (!$statusByCode(SEMESTER_STATUS_ACTIVE)) {
            consoleError('SemesterSeeder cannot proceed: SEMESTER_STATUS lookup values are missing.');
            return;
        }

        $semesters = [
            [
                'term' => 1,
                'name' => ['en' => 'Semester I', 'am' => 'አንደኛ ሴሚስተር'],
                'start_date' => '2025-09-08',
                'end_date' => '2026-01-23',
                'exam_start_date' => '2026-01-12',
                'exam_end_date' => '2026-01-23',
                'status' => SEMESTER_STATUS_CLOSED,
                'is_current' => false,
            ],
            [
                'term' => 2,
                'name' => ['en' => 'Semester II', 'am' => 'ሁለተኛ ሴሚስተር'],
                'start_date' => '2026-02-02',
                'end_date' => '2026-07-06',
                'exam_start_date' => '2026-06-22',
                'exam_end_date' => '2026-07-06',
                'status' => SEMESTER_STATUS_ACTIVE,
                'is_current' => true,
            ],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($semesters as $semester) {
                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = Semester::firstOrNew([
                    'academic_year_id' => $currentYear->id,
                    'term' => $semester['term'],
                ]);
                $row->fill([
                    'name' => [
                        English::getKey() => $semester['name']['en'],
                        Amharic::getKey() => $semester['name']['am'],
                    ],
                    'start_date' => $semester['start_date'],
                    'end_date' => $semester['end_date'],
                    'exam_start_date' => $semester['exam_start_date'],
                    'exam_end_date' => $semester['exam_end_date'],
                    'status_lookup_value_id' => $statusByCode($semester['status']),
                    'is_current' => $semester['is_current'],
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed semesters: ' . $exception->getMessage());
        }
    }
}
