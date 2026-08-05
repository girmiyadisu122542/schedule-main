<?php

namespace Database\Seeders\Offering;

use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseOfferingSeeder extends Seeder {

    /**
     * Seed offerings for the current semester across the whole approval chain,
     * so the trail (step 10) and the generators (steps 11/13) all have realistic
     * inputs: two already `registrar_approved` and ready to schedule, one mid-chain,
     * one still a draft.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('CourseOfferingSeeder cannot proceed: no user found.');
            return;
        }

        $semester = Semester::query()->where('is_current', true)->first();
        if (!$semester) {
            consoleError('CourseOfferingSeeder cannot proceed: run SemesterSeeder first.');
            return;
        }

        $courseByCode = fn (string $code): ?Course => Course::query()->where('code', $code)->first();
        $instructorByNo = fn (string $no): ?int => Instructor::query()->where('employee_no', $no)->value('id');
        $statusByCode = fn (string $code): ?int => LookupService::getValueByCode(COURSE_OFFERING_STATUS, $code, needId: true);

        if (!$courseByCode('CS101')) {
            consoleError('CourseOfferingSeeder cannot proceed: run CourseSeeder first.');
            return;
        }

        if (!$statusByCode(COURSE_OFFERING_STATUS_DRAFT)) {
            consoleError('CourseOfferingSeeder cannot proceed: COURSE_OFFERING_STATUS lookup values are missing.');
            return;
        }

        // Sections resolve by their natural key within the current academic year.
        $sectionOf = fn (string $programCode, int $yearLevel, string $label): ?Section => Section::query()
            ->where('academic_year_id', $semester->academic_year_id)
            ->where('year_level', $yearLevel)
            ->where('label', $label)
            ->whereHas('program', fn ($query) => $query->where('code', $programCode))
            ->first();

        $offerings = [
            ['course' => 'CS101', 'program' => 'BSC-CS', 'year' => 1, 'label' => 'A', 'instructor' => 'EMP-1001', 'students' => 45, 'status' => COURSE_OFFERING_STATUS_REGISTRAR_APPROVED],
            ['course' => 'CS101', 'program' => 'BSC-CS', 'year' => 1, 'label' => 'B', 'instructor' => 'EMP-1002', 'students' => 42, 'status' => COURSE_OFFERING_STATUS_REGISTRAR_APPROVED],
            ['course' => 'CS201', 'program' => 'BSC-CS', 'year' => 2, 'label' => 'A', 'instructor' => 'EMP-1002', 'students' => 38, 'status' => COURSE_OFFERING_STATUS_REGISTRAR_APPROVED],
            ['course' => 'SE301', 'program' => 'BSC-SE', 'year' => 2, 'label' => 'A', 'instructor' => 'EMP-1003', 'students' => 40, 'status' => COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED],
            ['course' => 'CS210', 'program' => 'BSC-CS', 'year' => 1, 'label' => 'A', 'instructor' => 'EMP-1001', 'students' => 45, 'status' => COURSE_OFFERING_STATUS_DRAFT],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($offerings as $offering) {
                $course = $courseByCode($offering['course']);
                $section = $sectionOf($offering['program'], $offering['year'], $offering['label']);

                if (!$course || !$section) {
                    continue;
                }

                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = CourseOffering::firstOrNew([
                    'semester_id' => $semester->id,
                    'course_id' => $course->id,
                    'section_id' => $section->id,
                ]);
                $isSubmitted = $offering['status'] !== COURSE_OFFERING_STATUS_DRAFT;
                $row->fill([
                    // A course is owned by the department that defined it.
                    'department_id' => $course->department_id,
                    'program_id' => $section->program_id,
                    'instructor_id' => $instructorByNo($offering['instructor']),
                    'expected_students' => $offering['students'],
                    'status_lookup_value_id' => $statusByCode($offering['status']),
                    'status_changed_at' => now(),
                    'created_by_id' => $user->id,
                    'submitted_by_id' => $isSubmitted ? $user->id : null,
                    'submitted_at' => $isSubmitted ? now() : null,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed course offerings: ' . $exception->getMessage());
        }
    }
}
