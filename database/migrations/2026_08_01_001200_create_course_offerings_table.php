<?php

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §12 course_offerings.
     *
     * The pivot of the whole system: a course planned for one semester, one
     * section, one instructor. Everything downstream — approvals, class
     * schedules, exam schedules — references an offering.
     *
     * A workflow table: creator is `created_by_id` (not `user_id`), and there is
     * no `deleted_at`, no `is_active` and no `state`.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(CourseOffering::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->integer('expected_students')->default(0);
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            // The offering carries no academic_year_id — it is reachable through
            // the semester, so no other table stores a second copy of it.
            $table->foreignId('semester_id')->constrained(Semester::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('course_id')->constrained(Course::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('department_id')->constrained(Department::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained(Program::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained(Section::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained(Instructor::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // Workflow actors — explicit names, never `user_id`.
            $table->foreignId('created_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['semester_id', 'course_id', 'section_id']);
            $table->index(['semester_id', 'department_id', 'status_lookup_value_id']);
            $table->index('status_lookup_value_id');
            $table->index('instructor_id');
            $table->index('section_id');
        });

        // A section-less offering (a whole-cohort lecture) may still appear only
        // once per semester. The composite unique above cannot enforce that:
        // PostgreSQL treats NULLs as distinct, so every section_id IS NULL row
        // would slip through.
        DB::statement('CREATE UNIQUE INDEX course_offerings_semester_course_no_section_unique ON course_offerings (semester_id, course_id) WHERE section_id IS NULL');

        // Helper uniques — the targets `class_schedules` and `exam_schedules`
        // point their composite FKs at, so a schedule row's mirrored
        // semester_id / section_id can never drift from the offering's own.
        // These must be real UNIQUE CONSTRAINTS, not plain indexes: a composite
        // FK can only reference a constraint.
        DB::statement('ALTER TABLE course_offerings ADD CONSTRAINT course_offerings_id_semester_unique UNIQUE (id, semester_id)');
        DB::statement('ALTER TABLE course_offerings ADD CONSTRAINT course_offerings_id_section_unique UNIQUE (id, section_id)');

        DB::statement('ALTER TABLE course_offerings ADD CONSTRAINT course_offerings_expected_students_check CHECK (expected_students >= 0)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS course_offerings_semester_course_no_section_unique');
        Schema::dropIfExists(CourseOffering::getTableName());
    }
};
