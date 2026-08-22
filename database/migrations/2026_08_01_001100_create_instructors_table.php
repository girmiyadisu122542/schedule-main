<?php

use App\Models\Academic\Department;
use App\Models\People\Instructor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §11 instructors.
     *
     * Instructors and invigilators are one population; two capability flags
     * distinguish the role without a second table.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Instructor::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('employee_no', 50)->unique();
            // A jsonb snapshot, so rosters and print artifacts stay correct even
            // for instructors with no linked users row.
            $table->jsonb('full_name');
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            // Free-form HR label; a candidate for a lookup type if it ever drives behaviour.
            $table->string('academic_rank', 40)->nullable();
            $table->boolean('can_teach')->default(true);
            $table->boolean('can_invigilate')->default(true);
            // A soft workload ceiling, checked in the service rather than by a constraint.
            $table->decimal('max_weekly_hours', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // THE PERSON this instructor is — nullable because the registry
            // precedes the login account. This table has NO creator column;
            // `user_id` here is an identity link, not `created_by`.
            $table->foreignId('user_id')->nullable()->unique()->constrained(User::getTableName())->nullOnDelete();
            $table->foreignId('department_id')->constrained(Department::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE instructors ADD CONSTRAINT instructors_max_weekly_hours_check CHECK (max_weekly_hours IS NULL OR max_weekly_hours > 0)');

        // Assignable teachers, and the invigilator pool. Partial indexes on
        // PostgreSQL; MySQL has no index predicate, so `is_active` leads the
        // index instead of filtering it.
        DB::statement('CREATE INDEX instructors_department_can_teach_active_index ON instructors (is_active, department_id, can_teach)');
        DB::statement('CREATE INDEX instructors_department_can_invigilate_active_index ON instructors (is_active, department_id, can_invigilate)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Instructor::getTableName());
    }
};
