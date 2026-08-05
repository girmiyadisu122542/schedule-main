<?php

use App\Models\Academic\Semester;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Models\People\Instructor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §17 invigilator_availabilities.
     *
     * A window in which the department declares an instructor available to
     * invigilate. A row means *available*; the absence of a row means *not
     * offered* — a positive declaration, which is why this table carries no
     * status, no `state`, no `is_active` and no soft delete.
     *
     * `submitted_by_id` is the department submitter, not a creator: there is no
     * `user_id` column here at all.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(InvigilatorAvailability::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->date('available_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('remark')->nullable();
            $table->timestamps();

            // Cascade: an instructor who leaves takes their offered windows with
            // them — an availability has no meaning without the person.
            $table->foreignId('instructor_id')->constrained(Instructor::getTableName())->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained(Semester::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('submitted_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['instructor_id', 'available_date', 'start_time', 'end_time']);
            $table->index(['instructor_id', 'available_date']);
            $table->index(['semester_id', 'available_date']);
        });

        DB::statement('ALTER TABLE invigilator_availabilities ADD CONSTRAINT invigilator_availabilities_time_check CHECK (end_time > start_time)');

        // No `WHERE state = 1` predicate — the sixth of the seven EXCLUDE
        // constraints is the only one that applies to EVERY row, because this
        // table has no liveness flag to read. That is what makes "is this
        // instructor free at 09:00 on 14 June?" have exactly one answer.
        DB::statement(<<<'SQL'
            ALTER TABLE invigilator_availabilities
            ADD CONSTRAINT ia_no_overlap
            EXCLUDE USING gist (
                instructor_id  WITH =,
                available_date WITH =,
                timerange(start_time, end_time) WITH &&
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(InvigilatorAvailability::getTableName());
    }
};
