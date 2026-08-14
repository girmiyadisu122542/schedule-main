<?php

use App\Models\Common\Lookup\LookupValue;
use App\Models\People\Instructor;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §14 class_schedules.
     *
     * One recurring weekly class meeting. An approved offering fans out into
     * one row per meeting (Monday lecture, Wednesday lab), each placed in a
     * room and a time.
     *
     * This replaces the starter kit's sample `class_schedules` table, which
     * owned the same name with a completely different shape (see the
     * build-plan's sample-replacement warning).
     *
     * @return void
     */
    public function up(): void {
        Schema::create(ClassSchedule::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();

            // Bare bigints, deliberately: their integrity comes from the two
            // composite foreign keys declared below, not from single-column
            // references. `semester_id` and `section_id` are copies of the
            // offering's own values — they exist so the conflict EXCLUDE
            // constraints can read them off this row.
            $table->unsignedBigInteger('course_offering_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('section_id')->nullable();

            $table->smallInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');

            // The constraint-liveness flag — NOT `is_active`. The three EXCLUDE
            // constraints read it, so cancelling a meeting (status -> cancelled,
            // state -> STATE_INACTIVE) frees its room/instructor/section slot
            // without deleting the row. Final Schema.md §14 design notes.
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);

            // A hand-placed row a coordinator does not want the next
            // generation run to take away. `clearDrafts()` skips these and the
            // generator treats them as occupied (C15).
            $table->boolean('is_pinned')->default(false);

            // Normally null: a class recurs weekly on `day_of_week`. Set for a
            // one-off — a makeup class on one date — in which case day_of_week
            // still holds that date's weekday so the EXCLUDE constraints and
            // every week-grid query keep working unchanged (C18).
            $table->date('specific_date')->nullable();

            // The department confirmation step (C26). Mirrors exam_schedules:
            // the department owns the teaching load, so it gets the same say
            // over the class timetable that it already has over the exams.
            $table->timestamp('confirmed_at')->nullable();
            $table->text('confirmation_remark')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreignId('instructor_id')->nullable()->constrained(Instructor::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained(Room::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('session_type_lookup_value_id')->nullable()->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            // Telemetry only — losing a run must not take the timetable with it.
            $table->foreignId('generation_run_id')->nullable()->constrained(ScheduleGenerationRun::getTableName())->nullOnDelete();

            $table->foreignId('created_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index('course_offering_id');
            $table->index(['semester_id', 'day_of_week', 'start_time']);
            $table->index(['room_id', 'day_of_week']);
            $table->index(['instructor_id', 'day_of_week']);
            $table->index(['semester_id', 'status_lookup_value_id']);
        });

        // Composite FKs — the mirrored semester_id / section_id cannot drift
        // from the offering's own, and ON UPDATE CASCADE propagates a change.
        // They target the helper UNIQUE CONSTRAINTS on course_offerings.
        DB::statement(<<<'SQL'
            ALTER TABLE class_schedules
            ADD CONSTRAINT class_schedules_offering_semester_foreign
            FOREIGN KEY (course_offering_id, semester_id)
            REFERENCES course_offerings (id, semester_id) ON UPDATE CASCADE
        SQL);
        // MATCH SIMPLE (the default) lets a section-less meeting through: with
        // section_id NULL the whole key is treated as satisfied.
        DB::statement(<<<'SQL'
            ALTER TABLE class_schedules
            ADD CONSTRAINT class_schedules_offering_section_foreign
            FOREIGN KEY (course_offering_id, section_id)
            REFERENCES course_offerings (id, section_id) ON UPDATE CASCADE
        SQL);

        DB::statement('ALTER TABLE class_schedules ADD CONSTRAINT class_schedules_day_of_week_check CHECK (day_of_week BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE class_schedules ADD CONSTRAINT class_schedules_time_check CHECK (end_time > start_time)');

        // ---- the conflict engine ----
        // Three EXCLUDE constraints, all predicated on the liveness flag. The
        // predicate cannot name a lookup value without hard-coding its id in
        // DDL, which is exactly why `state` exists alongside the status.
        $active = STATE_ACTIVE;

        DB::statement(<<<SQL
            ALTER TABLE class_schedules
            ADD CONSTRAINT cs_no_instructor_clash
            EXCLUDE USING gist (
                semester_id   WITH =,
                instructor_id WITH =,
                day_of_week   WITH =,
                timerange(start_time, end_time) WITH &&
            ) WHERE (state = {$active} AND instructor_id IS NOT NULL)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE class_schedules
            ADD CONSTRAINT cs_no_room_clash
            EXCLUDE USING gist (
                semester_id WITH =,
                room_id     WITH =,
                day_of_week WITH =,
                timerange(start_time, end_time) WITH &&
            ) WHERE (state = {$active} AND room_id IS NOT NULL)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE class_schedules
            ADD CONSTRAINT cs_no_section_clash
            EXCLUDE USING gist (
                semester_id WITH =,
                section_id  WITH =,
                day_of_week WITH =,
                timerange(start_time, end_time) WITH &&
            ) WHERE (state = {$active} AND section_id IS NOT NULL)
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(ClassSchedule::getTableName());
    }
};
