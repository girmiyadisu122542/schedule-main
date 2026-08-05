<?php

namespace Database\Seeders\Academic;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SectionSeeder extends Seeder {

    /**
     * Seed student cohorts for the current academic year. Program FKs resolve by
     * stable code, never by an auto-increment id.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('SectionSeeder cannot proceed: no user found.');
            return;
        }

        $currentYear = AcademicYear::query()->where('is_current', true)->first();
        if (!$currentYear) {
            consoleError('SectionSeeder cannot proceed: run AcademicYearSeeder first.');
            return;
        }

        $programByCode = fn (string $code): ?int => Program::query()->where('code', $code)->value('id');

        if (!$programByCode('BSC-CS')) {
            consoleError('SectionSeeder cannot proceed: run ProgramSeeder first.');
            return;
        }

        $sections = [
            ['program_code' => 'BSC-CS', 'year_level' => 1, 'label' => 'A', 'expected_students' => 45],
            ['program_code' => 'BSC-CS', 'year_level' => 1, 'label' => 'B', 'expected_students' => 42],
            ['program_code' => 'BSC-CS', 'year_level' => 2, 'label' => 'A', 'expected_students' => 38],
            ['program_code' => 'BSC-SE', 'year_level' => 2, 'label' => 'A', 'expected_students' => 40],
            ['program_code' => 'MSC-CS', 'year_level' => 1, 'label' => 'A', 'expected_students' => 18],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($sections as $section) {
                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = Section::firstOrNew([
                    'program_id' => $programByCode($section['program_code']),
                    'academic_year_id' => $currentYear->id,
                    'year_level' => $section['year_level'],
                    'label' => $section['label'],
                ]);
                $row->fill([
                    'expected_students' => $section['expected_students'],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed sections: ' . $exception->getMessage());
        }
    }
}
