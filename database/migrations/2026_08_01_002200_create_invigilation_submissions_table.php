<?php

use App\Models\Invigilation\InvigilationRequestDepartment;
use App\Models\Invigilation\InvigilationSubmission;
use App\Models\People\Instructor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. One person a department has offered for one request.
     *
     * This is the roster the exam scheduler draws from: an instructor becomes a
     * candidate for a sitting by having been SUBMITTED for the request covering
     * it, not by merely existing in the staff list.
     *
     * A withdrawal is a delete, not a status: an offer taken back leaves no
     * decision trail worth keeping, unlike an assignment, which records that
     * somebody was once on duty.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(InvigilationSubmission::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->text('remark')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            // Cascade: a submission is meaningless without the department share
            // it answers.
            $table->foreignId('invigilation_request_department_id')
                ->constrained(InvigilationRequestDepartment::getTableName())
                ->restrictOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained(Instructor::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('submitted_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // The same person offered twice against one ask is a double count,
            // and would quietly inflate the fulfilment figure.
            $table->unique(['invigilation_request_department_id', 'instructor_id'], 'invigilation_submission_unique');
            // The pool query reads by instructor.
            $table->index('instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(InvigilationSubmission::getTableName());
    }
};
