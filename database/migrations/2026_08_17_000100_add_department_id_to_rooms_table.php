<?php

use App\Models\Academic\Department;
use App\Models\Physical\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The department a room belongs to.
     *
     * A room has at most ONE owning department — a shared lab is given to the
     * department that runs it, not to both. That is why this is a column rather
     * than a pivot: "which department owns this room" has exactly one answer,
     * and a pivot would have allowed two departments to each believe the room
     * was theirs to fill.
     *
     * NULLABLE, deliberately, and it carries meaning:
     *
     *   - a room with a department is that department's to schedule, and only
     *     theirs — the schedule services reject any other department's class or
     *     exam in it;
     *   - a room with NULL belongs to nobody and is scheduled by nobody. It is
     *     not a free-for-all pool: an unassigned room is simply not yet given
     *     out.
     *
     * A department with no rooms at all does not fail to schedule — its classes
     * are placed with `room_id` NULL, carrying the course and the time, and a
     * coordinator assigns the room later.
     *
     * `nullOnDelete` rather than restrict: deleting a department releases its
     * rooms back to unassigned instead of blocking on them.
     *
     * @return void
     */
    public function up(): void {
        Schema::table(Room::getTableName(), function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('building_id')
                ->constrained(Department::getTableName())
                ->restrictOnUpdate()
                ->nullOnDelete();

            // The scheduler's first question about a room: whose is it, and is
            // it usable? Both generators read the pool through this.
            $table->index(['department_id', 'is_active'], 'rooms_department_active_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::table(Room::getTableName(), function (Blueprint $table) {
            $table->dropIndex('rooms_department_active_index');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
