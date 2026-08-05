<?php

use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §16 schedule_generation_runs.
     *
     * Telemetry for one automatic-scheduling execution — who ran it, for which
     * semester, and what came out. It holds no timetable data: the schedules
     * point back at it through a nullable `generation_run_id`, never the other
     * way round on a hot path.
     *
     * A workflow table: creator is `run_by_id`, and there is no `is_active`,
     * no `state`, no `deleted_at`.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(ScheduleGenerationRun::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->integer('scheduled_count')->default(0);
            $table->integer('unplaced_count')->default(0);
            $table->integer('duration_seconds')->nullable();
            // Per-run detail the progress UI reads: which offerings were placed,
            // and the error key for each one that was not.
            $table->jsonb('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreignId('semester_id')->constrained(Semester::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('type_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('run_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index(['semester_id', 'type_lookup_value_id', 'started_at']);
        });

        DB::statement('ALTER TABLE schedule_generation_runs ADD CONSTRAINT schedule_generation_runs_scheduled_count_check CHECK (scheduled_count >= 0)');
        DB::statement('ALTER TABLE schedule_generation_runs ADD CONSTRAINT schedule_generation_runs_unplaced_count_check CHECK (unplaced_count >= 0)');
        DB::statement('ALTER TABLE schedule_generation_runs ADD CONSTRAINT schedule_generation_runs_duration_seconds_check CHECK (duration_seconds IS NULL OR duration_seconds >= 0)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(ScheduleGenerationRun::getTableName());
    }
};
