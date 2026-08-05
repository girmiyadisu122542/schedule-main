<?php

use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §5 programs.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Program::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 30)->unique();
            $table->jsonb('name');
            $table->smallInteger('duration_years');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('department_id')->constrained(Department::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('degree_level_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index(['department_id', 'is_active']);
        });

        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_duration_years_check CHECK (duration_years BETWEEN 1 AND 10)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Program::getTableName());
    }
};
