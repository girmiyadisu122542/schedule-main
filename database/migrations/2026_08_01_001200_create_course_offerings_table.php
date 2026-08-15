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

            // NOT a unique on (semester_id, course_id, section_id): MySQL, like
            // PostgreSQL, treats NULLs as distinct, so every section-less row
            // would slip through it. The real uniqueness is declared below on a
            // generated column that folds NULL down to 0.
            // Named explicitly: the generated name would be 71 characters, and
            // MySQL rejects an identifier over 64 (PostgreSQL silently truncated
            // at 63, so this only surfaces on MySQL).
            $table->index(['semester_id', 'department_id', 'status_lookup_value_id'], 'course_offerings_semester_dept_status_index');
            $table->index('status_lookup_value_id');
            $table->index('instructor_id');
            $table->index('section_id');
        });

        // One offering per semester + course + section, AND — because a
        // section-less offering is a whole-cohort lecture — only one such row
        // per semester + course. PostgreSQL needed a plain unique plus a
        // partial unique for that; MySQL has no partial index, so folding NULL
        // to 0 in a generated column expresses both rules in one index.
        DB::statement(
            'ALTER TABLE course_offerings'
            . ' ADD COLUMN section_unique_key BIGINT UNSIGNED'
            . ' GENERATED ALWAYS AS (COALESCE(section_id, 0)) STORED'
        );
        DB::statement(
            'CREATE UNIQUE INDEX course_offerings_semester_course_section_unique'
            . ' ON course_offerings (semester_id, course_id, section_unique_key)'
        );

        // Helper uniques — the targets `class_schedules` and `exam_schedules`
        // point their composite FKs at, so a schedule row's mirrored
        // semester_id / section_id can never drift from the offering's own.
        // InnoDB requires the referenced columns to be indexed; a unique index
        // is what makes the reference one-to-one.
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
        Schema::dropIfExists(CourseOffering::getTableName());
    }
};
