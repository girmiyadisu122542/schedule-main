<?php

use App\Models\Common\Lookup\LookupValue;
use App\Models\Offering\CourseOffering;
use App\Models\Offering\CourseOfferingApproval;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §13 course_offering_approvals.
     *
     * The append-only decision trail for the four-tier chain. One table, four
     * APPROVAL_LEVEL values — the tiers differ only in *who acts*, which is a
     * value, not a structure.
     *
     * Append-only is literal: a reversal is a new row, never an edit. So the
     * table has `created_at` alone — no `updated_at` (do NOT call timestamps())
     * and no soft delete.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(CourseOfferingApproval::getTableName(), function (Blueprint $table) {
            $table->id();
            // Order of the entry within one offering's trail.
            $table->smallInteger('sequence');
            $table->timestamp('acted_at')->useCurrent();
            // Required on reject / revision — a value-conditional rule a foreign
            // key cannot express, so it lives in the Form Request.
            $table->text('remark')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Meaningless without its offering, so this is one of the two
            // cascade relationships in the whole schema.
            $table->foreignId('course_offering_id')->constrained(CourseOffering::getTableName())->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('level_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('decision_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('acted_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // The trail in order, and one actor's history.
            $table->index(['course_offering_id', 'acted_at']);
            $table->index(['acted_by_id', 'acted_at']);
        });

        // One decision per tier per offering: a tier that changes its mind
        // rejects, and the rework comes back as a fresh pass through the chain.
        $table = CourseOfferingApproval::getTableName();
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT course_offering_approvals_sequence_check CHECK (sequence > 0)");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT course_offering_approvals_offering_sequence_unique UNIQUE (course_offering_id, sequence)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(CourseOfferingApproval::getTableName());
    }
};
