<?php

use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ClassScheduleException;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. One week's cancellation of a recurring class.
     *
     * A `class_schedules` row is a weekly rule. Cancelling it outright removes
     * every week; there was no way to say "not this Monday — it is a public
     * holiday" short of deleting and re-creating the row, which loses its
     * history and its generation-run link.
     *
     * An exception suppresses exactly one occurrence. It deliberately does NOT
     * touch `state`: the weekly rule stays live, so the room and the instructor
     * remain booked for every other week and the EXCLUDE constraints keep
     * protecting them.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(ClassScheduleException::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            // The single occurrence being suppressed.
            $table->date('exception_date');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreignId('class_schedule_id')->constrained(ClassSchedule::getTableName())->restrictOnUpdate()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // Cancelling the same week twice is a no-op, not two records.
            $table->unique(['class_schedule_id', 'exception_date'], 'cse_schedule_date_unique');
            $table->index('exception_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(ClassScheduleException::getTableName());
    }
};
