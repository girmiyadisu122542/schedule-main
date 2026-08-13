<?php

use App\Models\Common\Lookup\LookupValue;
use App\Models\Schedule\ScheduleSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. The generation grid, per study mode.
     *
     * Everything the class generator used to read from
     * `App\Constants\ScheduleConstant` lives here instead, so a registrar can
     * make Saturday a working day, move lunch, or lengthen a period without a
     * redeploy. One row per STUDY_MODE: regular students sit Monday–Friday in
     * the day, extension students at the weekend.
     *
     * The daily grid is DERIVED rather than listed: a start, an end, a period
     * length and a break length generate the periods, and the declared lunch
     * window is cut out of them. Storing the periods themselves would let the
     * lunch break drift out of the middle of the day unnoticed, which is
     * exactly what happened while the grid was a hand-written constant.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(ScheduleSetting::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            // The teaching days as ISO-8601 numbers, 1 = Monday .. 7 = Sunday.
            $table->jsonb('teaching_days');
            $table->time('day_start');
            $table->time('day_end');
            $table->smallInteger('period_minutes');
            $table->smallInteger('break_minutes')->default(0);
            // Nullable together: an evening or weekend timetable need not break
            // for lunch at all.
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            // ---- the exam half ----
            // Exams do not sit on the teaching grid: a sitting runs hours, not
            // a period, and its length is a property of the COURSE, so only the
            // window and the turnaround are configured here.
            $table->jsonb('exam_days');
            $table->time('exam_day_start');
            $table->time('exam_day_end');
            // Used when a course declares no exam length of its own.
            $table->smallInteger('exam_duration_minutes');
            // Turnaround between sittings in the same hall.
            $table->smallInteger('exam_gap_minutes')->default(0);
            // How many days before the semester ends the exam period opens.
            $table->smallInteger('exam_period_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One grid per study mode, so a programme resolves exactly one.
            $table->foreignId('study_mode_lookup_value_id')->unique()->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });

        // A day that ends before it starts, or a period of no length, would
        // make the generator emit nothing and say nothing about why.
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_day_window_check CHECK (day_end > day_start)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_period_minutes_check CHECK (period_minutes BETWEEN 15 AND 480)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_break_minutes_check CHECK (break_minutes BETWEEN 0 AND 120)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_exam_window_check CHECK (exam_day_end > exam_day_start)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_exam_duration_check CHECK (exam_duration_minutes BETWEEN 15 AND 480)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_exam_gap_check CHECK (exam_gap_minutes BETWEEN 0 AND 240)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_exam_period_days_check CHECK (exam_period_days BETWEEN 1 AND 90)');

        // Lunch is both-or-neither, and must be a real window when given.
        //
        // `lunch_end IS NOT NULL` is not redundant: a CHECK passes when its
        // expression evaluates to NULL rather than FALSE, so without it a row
        // with a lunch_start and no lunch_end makes the comparison NULL and
        // slips straight through.
        DB::statement(
            'ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_lunch_check CHECK ('
            . '(lunch_start IS NULL AND lunch_end IS NULL)'
            . ' OR (lunch_start IS NOT NULL AND lunch_end IS NOT NULL AND lunch_end > lunch_start))'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(ScheduleSetting::getTableName());
    }
};
