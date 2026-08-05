<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §7 semesters.
     *
     * No `deleted_at`, no `is_active`, no `state` — a semester is the scheduling
     * unit itself; its only lifecycle is the guarded SEMESTER_STATUS move
     * (planning → scheduling → active → closed).
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Semester::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->smallInteger('term');
            $table->jsonb('name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->foreignId('academic_year_id')->constrained(AcademicYear::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // One "Semester 1" per academic year.
            $table->unique(['academic_year_id', 'term']);
            $table->index(['academic_year_id', 'status_lookup_value_id']);
        });

        DB::statement('ALTER TABLE semesters ADD CONSTRAINT semesters_dates_check CHECK (end_date > start_date)');
        DB::statement('ALTER TABLE semesters ADD CONSTRAINT semesters_term_check CHECK (term IN (1, 2, 3))');

        // Exactly one current semester across the whole institution.
        DB::statement('CREATE UNIQUE INDEX semesters_is_current_unique ON semesters (is_current) WHERE is_current = true');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS semesters_is_current_unique');
        Schema::dropIfExists(Semester::getTableName());
    }
};
