<?php

use App\Models\Schedule\ClassSchedule;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(ClassSchedule::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->jsonb('name');
            $table->string('code')->unique();
            $table->unsignedSmallInteger('schedule_type'); // ScheduleConstant::TYPE_CLASS | TYPE_EXAM
            $table->unsignedSmallInteger('day_of_week')->nullable(); // 1 (Mon) .. 7 (Sun), class schedules only
            $table->date('exam_date')->nullable(); // exam schedules only
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->string('section')->nullable();
            $table->unsignedSmallInteger('state');
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(ClassSchedule::getTableName());
    }
};
