<?php

use App\Models\Physical\Building;
use App\Models\Physical\Campus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §2 buildings.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Building::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 20)->unique();
            $table->jsonb('name');
            $table->smallInteger('floors')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('campus_id')->constrained(Campus::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index(['campus_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Building::getTableName());
    }
};
