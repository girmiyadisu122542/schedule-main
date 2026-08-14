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
            // Per exam type, keyed by the lookup CODE: {"midterm": 90, "final": 180}.
            // A midterm is almost never as long as a final, and the length is a
            // property of the type as much as of the course. Resolution order
            // is course -> type -> this default (ScheduleSettingService).
            $table->jsonb('exam_type_durations')->nullable();
            // Turnaround between sittings in the same hall.
            $table->smallInteger('exam_gap_minutes')->default(0);

            // ---- what a cohort may be put through (C8) ----
            // Overlapping exams are already impossible (es_no_section_clash).
            // These two stop the legal-but-brutal case: three papers in one day,
            // or a second paper starting as the first one ends.
            $table->smallInteger('max_exams_per_day')->default(2);
            $table->smallInteger('min_hours_between_exams')->default(0);

            // ---- invigilator staffing (C11) ----
            // A hall's duty count is derived from how many sit in it rather
            // than typed, so the quantities asked of departments are defensible.
            //
            // 50 is deliberate: a hall of forty needs one person, and only a
            // genuinely large sitting needs a second. Deriving it is the
            // starting point, not the last word — a registrar adds or removes
            // people on a specific sitting whenever the hall calls for it.
            $table->smallInteger('students_per_invigilator')->default(50);
            $table->smallInteger('min_invigilators_per_room')->default(1);

            // ---- soft constraints (C10) ----
            // Placement scores surviving candidates instead of taking the first
            // free slot. Zero switches a preference off entirely, which is the
            // documented way to opt out without code.
            $table->smallInteger('weight_spread_sessions')->default(10);
            $table->smallInteger('weight_avoid_gaps')->default(6);
            $table->smallInteger('weight_room_fit')->default(3);
            $table->smallInteger('weight_same_building')->default(4);
            // A cohort sent between campuses between periods cannot arrive.
            // Buildings are a preference; campuses are a hard rejection.
            $table->boolean('allow_cross_campus_day')->default(false);

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
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_max_exams_per_day_check CHECK (max_exams_per_day BETWEEN 1 AND 8)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_min_hours_between_exams_check CHECK (min_hours_between_exams BETWEEN 0 AND 72)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_students_per_invigilator_check CHECK (students_per_invigilator BETWEEN 5 AND 200)');
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_min_invigilators_check CHECK (min_invigilators_per_room BETWEEN 1 AND 20)');
        // Weights are preferences, not scores to tune without bound — a weight
        // an order of magnitude above the others silently becomes a hard rule.
        DB::statement('ALTER TABLE schedule_settings ADD CONSTRAINT schedule_settings_weights_check CHECK ('
            . 'weight_spread_sessions BETWEEN 0 AND 100 AND weight_avoid_gaps BETWEEN 0 AND 100'
            . ' AND weight_room_fit BETWEEN 0 AND 100 AND weight_same_building BETWEEN 0 AND 100)');

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
