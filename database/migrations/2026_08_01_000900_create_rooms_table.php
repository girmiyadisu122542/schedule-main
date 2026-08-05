<?php

use App\Models\Common\Lookup\LookupValue;
use App\Models\Physical\Building;
use App\Models\Physical\Room;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §9 rooms.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Room::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 30)->unique();
            $table->jsonb('name')->nullable();
            // Signed so basements are honest.
            $table->smallInteger('floor')->nullable();
            $table->integer('capacity');
            // Spaced exam seating is roughly half a hall's teaching capacity, so
            // a single number would either overbook every exam or waste half of
            // every classroom.
            $table->integer('exam_capacity')->nullable();
            // A use-flag independent of room_type: a large lecture_hall may serve
            // as an exam venue, so eligibility is not derived from the type.
            $table->boolean('is_exam_venue')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('building_id')->constrained(Building::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('room_type_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index('building_id');
            $table->index('room_type_lookup_value_id');
        });

        DB::statement('ALTER TABLE rooms ADD CONSTRAINT rooms_capacity_check CHECK (capacity > 0)');
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT rooms_exam_capacity_check CHECK (exam_capacity IS NULL OR exam_capacity > 0)');

        // Room search: "an active lecture hall seating at least 60".
        DB::statement('CREATE INDEX rooms_type_capacity_active_index ON rooms (room_type_lookup_value_id, capacity) WHERE is_active = true');
        // Exam venue search: "an active exam venue seating at least 40 spaced".
        DB::statement('CREATE INDEX rooms_exam_venue_capacity_active_index ON rooms (is_exam_venue, exam_capacity) WHERE is_active = true');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS rooms_exam_venue_capacity_active_index');
        DB::statement('DROP INDEX IF EXISTS rooms_type_capacity_active_index');
        Schema::dropIfExists(Room::getTableName());
    }
};
