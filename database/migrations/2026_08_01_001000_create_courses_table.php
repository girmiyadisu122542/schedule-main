<?php

use App\Models\Academic\Department;
use App\Models\Catalogue\Course;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §10 courses.
     *
     * The reusable catalogue: defined once, offered many times. It holds no
     * semester, instructor or enrolment — that is what makes it reusable.
     * Ownership is `department_id` (a department, never a program).
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Course::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            // Globally unique: it prints bare on timetables and exam papers
            // ("CS101"), where a reader has no department context to disambiguate.
            $table->string('code', 30)->unique();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->decimal('credit_hours', 4, 2);
            $table->decimal('contact_hours', 4, 2)->nullable();

            // Weekly-load columns the class generator fans out into meetings.
            $table->decimal('lecture_hours_per_week', 4, 2)->nullable();
            $table->decimal('lab_hours_per_week', 4, 2)->nullable();
            $table->decimal('tutorial_hours_per_week', 4, 2)->nullable();
            $table->smallInteger('sessions_per_week')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('department_id')->constrained(Department::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('course_type_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index(['department_id', 'is_active']);
        });

        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_credit_hours_check CHECK (credit_hours > 0)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_contact_hours_check CHECK (contact_hours IS NULL OR contact_hours > 0)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_sessions_per_week_check CHECK (sessions_per_week IS NULL OR sessions_per_week > 0)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Course::getTableName());
    }
};
