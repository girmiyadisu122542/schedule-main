<?php

use App\Models\Academic\Department;
use App\Models\Invigilation\InvigilationRequest;
use App\Models\Invigilation\InvigilationRequestDepartment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. One department's share of one request.
     *
     * This table exists because the quantity is PER DEPARTMENT: asking
     * Computer Science for ten invigilators and Accounting for four is one
     * request with two different numbers, which a single column on
     * `invigilation_requests` could not hold.
     *
     * There is no stored status or submitted count. Both are functions of
     * `required_count` and the rows in `invigilation_submissions` — storing
     * them would be a second copy of the same fact, free to drift the first
     * time a submission is withdrawn.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(InvigilationRequestDepartment::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->smallInteger('required_count');
            $table->timestamps();

            // Cascade: a department's share is meaningless without the request
            // it belongs to (Final Schema.md conventions).
            $table->foreignId('invigilation_request_id')
                ->constrained(InvigilationRequest::getTableName())
                ->restrictOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(Department::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // One share per department per request — asking the same department
            // twice in one request is a mistake, not a second ask.
            $table->unique(['invigilation_request_id', 'department_id'], 'invigilation_request_department_unique');
            // The department's own inbox reads by this.
            $table->index('department_id');
        });

        // Asking a department for nobody is not an ask.
        DB::statement(
            'ALTER TABLE invigilation_request_departments'
            . ' ADD CONSTRAINT invigilation_request_departments_required_count_check CHECK (required_count > 0)'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(InvigilationRequestDepartment::getTableName());
    }
};
