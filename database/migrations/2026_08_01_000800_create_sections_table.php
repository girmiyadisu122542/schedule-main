<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §8 sections.
     *
     * A section is scoped to the ACADEMIC YEAR, not the semester — "CS Year 2
     * Section A" is the same cohort across both semesters of the year.
     * No `deleted_at` and no status: `is_active` is the whole lifecycle.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Section::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->smallInteger('year_level');
            $table->string('label', 10);
            $table->integer('expected_students')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreignId('program_id')->constrained(Program::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained(AcademicYear::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['program_id', 'academic_year_id', 'year_level', 'label']);
            $table->index(['academic_year_id', 'program_id', 'year_level']);
        });

        DB::statement('ALTER TABLE sections ADD CONSTRAINT sections_year_level_check CHECK (year_level BETWEEN 1 AND 10)');
        DB::statement('ALTER TABLE sections ADD CONSTRAINT sections_expected_students_check CHECK (expected_students >= 0)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Section::getTableName());
    }
};
