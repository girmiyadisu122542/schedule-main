<?php

use App\Models\Academic\AcademicYear;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §6 academic_years.
     *
     * Deliberately has no `is_active` and no `deleted_at` — an academic year is
     * a period, not a record you retire; every semester and section hangs off it.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(AcademicYear::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE academic_years ADD CONSTRAINT academic_years_dates_check CHECK (end_date > start_date)');

        // Exactly one current year — a partial unique index is the only way to
        // say "at most one true" without also blocking the false rows.
        DB::statement('CREATE UNIQUE INDEX academic_years_is_current_unique ON academic_years (is_current) WHERE is_current = true');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS academic_years_is_current_unique');
        Schema::dropIfExists(AcademicYear::getTableName());
    }
};
