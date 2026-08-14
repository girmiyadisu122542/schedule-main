<?php

use App\Models\Common\Lookup\LookupValue;
use App\Models\Physical\Room;
use App\Models\Schedule\ExamSchedule;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §15 exam_schedules.
     *
     * One exam sitting. Unlike a class meeting this is a single dated event, so
     * its conflict constraints overlap on a `tsrange` built from the date and
     * the times rather than on a weekly `timerange`.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(ExamSchedule::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();

            // Bare bigints, guarded by the two composite foreign keys below —
            // the same mirroring `class_schedules` uses, and for the same
            // reason: the EXCLUDE constraints read them off this row.
            $table->unsignedBigInteger('course_offering_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('section_id')->nullable();

            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            // Sizes the invigilation step (steps 14/15).
            $table->smallInteger('required_invigilators')->default(1);

            // ---- one paper sat across several halls (C9) ----
            // A 300-strong cohort and no 300-seat hall is the normal case, not
            // the exception. The sitting is then split: one row per hall, all
            // at the same date and time, each seating `seat_allocation` of the
            // cohort. `part_number of part_count` is what a student reads.
            $table->integer('seat_allocation')->nullable();
            $table->smallInteger('part_number')->default(1);
            $table->smallInteger('part_count')->default(1);

            // A hand-placed sitting the next run must not move (C15).
            $table->boolean('is_pinned')->default(false);

            // ---- accommodations (C21) ----
            // Without a student entity this is what can honestly be offered:
            // the registrar records what is needed and reserves a second room
            // for it, and the duty roster prints both.
            $table->text('accommodation_note')->nullable();
            $table->smallInteger('accommodation_extra_minutes')->nullable();

            // The constraint-liveness flag — NOT `is_active`.
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);

            $table->timestamp('confirmed_at')->nullable();
            $table->text('confirmation_remark')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreignId('room_id')->nullable()->constrained(Room::getTableName())->restrictOnUpdate()->restrictOnDelete();
            // The separate room an accommodation is sat in. Not covered by the
            // room EXCLUDE: it is a reservation, not a scheduled sitting.
            $table->foreignId('accommodation_room_id')->nullable()->constrained(Room::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('exam_type_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained(ScheduleGenerationRun::getTableName())->nullOnDelete();

            $table->foreignId('created_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            // The department confirmation actor — a distinct step from publishing.
            $table->foreignId('confirmed_by_id')->nullable()->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // One final, one midterm, … per offering.
            // One sitting per offering per exam type — except that a paper
            // split across halls is several rows of one sitting, distinguished
            // by `part_number`. Without part_number here the second hall is
            // rejected as a duplicate exam (C9).
            $table->unique(['course_offering_id', 'exam_type_lookup_value_id', 'part_number'], 'exam_schedules_offering_type_part_unique');

            $table->index('course_offering_id');
            $table->index(['semester_id', 'exam_date', 'start_time']);
            $table->index(['room_id', 'exam_date']);
            $table->index(['semester_id', 'status_lookup_value_id']);
        });

        // Composite FKs — the mirrored semester_id / section_id cannot drift
        // from the offering's own.
        DB::statement(<<<'SQL'
            ALTER TABLE exam_schedules
            ADD CONSTRAINT exam_schedules_offering_semester_foreign
            FOREIGN KEY (course_offering_id, semester_id)
            REFERENCES course_offerings (id, semester_id) ON UPDATE CASCADE
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE exam_schedules
            ADD CONSTRAINT exam_schedules_offering_section_foreign
            FOREIGN KEY (course_offering_id, section_id)
            REFERENCES course_offerings (id, section_id) ON UPDATE CASCADE
        SQL);

        // Helper unique — the target `exam_invigilator_assignments` points its
        // composite FK at (step 15), so an assignment's mirrored date and times
        // can never drift from the sitting's own. It must be a real UNIQUE
        // CONSTRAINT: a composite FK cannot reference a plain index.
        DB::statement('ALTER TABLE exam_schedules ADD CONSTRAINT exam_schedules_id_window_unique UNIQUE (id, exam_date, start_time, end_time)');

        DB::statement('ALTER TABLE exam_schedules ADD CONSTRAINT exam_schedules_time_check CHECK (end_time > start_time)');
        DB::statement('ALTER TABLE exam_schedules ADD CONSTRAINT exam_schedules_required_invigilators_check CHECK (required_invigilators >= 1)');

        // ---- the conflict engine ----
        // Exams overlap on a tsrange built from the date and the times: two
        // sittings on different days never conflict, however close their clock
        // times are.
        $active = STATE_ACTIVE;

        DB::statement(<<<SQL
            ALTER TABLE exam_schedules
            ADD CONSTRAINT es_no_room_clash
            EXCLUDE USING gist (
                room_id WITH =,
                tsrange(exam_date + start_time, exam_date + end_time) WITH &&
            ) WHERE (state = {$active} AND room_id IS NOT NULL)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE exam_schedules
            ADD CONSTRAINT es_no_section_clash
            EXCLUDE USING gist (
                section_id WITH =,
                tsrange(exam_date + start_time, exam_date + end_time) WITH &&
            ) WHERE (state = {$active} AND section_id IS NOT NULL)
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(ExamSchedule::getTableName());
    }
};
