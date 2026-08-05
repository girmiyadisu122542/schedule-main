<?php

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §4 departments.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Department::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 20)->unique();
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('college_id')->constrained(College::getTableName())->restrictOnUpdate()->restrictOnDelete();
            // Routing pointer for the department-approval step — see colleges.dean_user_id.
            $table->foreignId('head_user_id')->nullable()->constrained(User::getTableName())->nullOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index(['college_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Department::getTableName());
    }
};
