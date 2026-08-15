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
            // Named explicitly — the generated name exceeds MySQL's 64-character
            // identifier limit. ExamInvigilatorAssignmentService::CONFLICT_KEYS
            // matches on this name, so the two must stay in step.
            $table->unique(['exam_schedule_id', 'instructor_id'], 'eia_exam_instructor_unique');

            $table->index('exam_schedule_id');
            // Also what the double-booking check in ScheduleConflictGuard reads.
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

        // An invigilator cannot be at two exams at once. This was a GiST EXCLUDE
        // constraint on PostgreSQL; MySQL cannot express it, so
        // ExamInvigilatorAssignmentService enforces it through
        // ScheduleConflictGuard inside its write transaction.
        //
        // One guarantee genuinely does not survive the port: the ON UPDATE
        // CASCADE above still moves every duty row when a sitting is
        // rescheduled, but MySQL will no longer re-check double-booking as it
        // cascades. ExamScheduleService therefore has to re-validate the duty
        // roster itself after moving a sitting.
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
