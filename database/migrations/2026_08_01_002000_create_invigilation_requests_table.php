<?php

use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Invigilation\InvigilationRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. The registrar's ask for invigilators.
     *
     * One request covers one examination scope — a semester and an exam type,
     * which together are what an institution calls "the mid-semester
     * examination". There is deliberately no `exam_groups` table: the scope
     * already exists as those two columns, and every exam sitting is already
     * filtered by them.
     *
     * The per-department quantities live in the child table, because different
     * departments are asked for different numbers and a single figure on this
     * row could not express that.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(InvigilationRequest::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->text('remark')->nullable();
            // Stamped when the request leaves draft — the moment departments
            // may start responding.
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreignId('semester_id')->constrained(Semester::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('exam_type_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // The registrar's list, and the lookup the pool query makes for
            // every sitting it staffs.
            $table->index(['semester_id', 'exam_type_lookup_value_id', 'status_lookup_value_id'], 'invigilation_requests_scope_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(InvigilationRequest::getTableName());
    }
};
