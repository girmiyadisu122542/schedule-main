<?php

use App\Models\Common\Lookup\LookupValue;
use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Models\People\Instructor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §18 exam_invigilator_assignments.
     *
     * One instructor on duty at one exam.
     *
     * The exam's date and times are mirrored here and guarded by a composite
     * foreign key, so they cannot disagree with the sitting. `ON UPDATE CASCADE`
     * means rescheduling an exam moves every duty row's datetime with it AND
     * re-checks the double-booking constraint against the new time — with no
     * application code involved.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(ExamInvigilatorAssignment::getTableName(), function (Blueprint $table) {
            $table->id();

            // A bare bigint: its integrity comes from the composite FK below,
            // which also carries the cascade behaviour.
            $table->unsignedBigInteger('exam_schedule_id');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');

            // The constraint-liveness flag — a declined or replaced assignment
            // is set to STATE_INACTIVE, drops out of eia_no_double_booking and
            // frees the invigilator, without deleting the record of what
            // happened.
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);

            $table->timestamp('assigned_at')->useCurrent();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreignId('instructor_id')->constrained(Instructor::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('role_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('assigned_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // Nobody is assigned twice to one exam.
            $table->unique(['exam_schedule_id', 'instructor_id']);

            $table->index('exam_schedule_id');
            $table->index(['instructor_id', 'exam_date']);
        });

        // The composite FK targets the helper unique on exam_schedules.
        DB::statement(<<<'SQL'
            ALTER TABLE exam_invigilator_assignments
            ADD CONSTRAINT exam_invigilator_assignments_exam_window_foreign
            FOREIGN KEY (exam_schedule_id, exam_date, start_time, end_time)
            REFERENCES exam_schedules (id, exam_date, start_time, end_time)
            ON UPDATE CASCADE ON DELETE CASCADE
        SQL);

        // An invigilator cannot be at two exams at once. Same tsrange overlap as
        // the exam constraints, because a duty is a single dated event too.
        $active = STATE_ACTIVE;

        DB::statement(<<<SQL
            ALTER TABLE exam_invigilator_assignments
            ADD CONSTRAINT eia_no_double_booking
            EXCLUDE USING gist (
                instructor_id WITH =,
                tsrange(exam_date + start_time, exam_date + end_time) WITH &&
            ) WHERE (state = {$active})
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(ExamInvigilatorAssignment::getTableName());
    }
};
