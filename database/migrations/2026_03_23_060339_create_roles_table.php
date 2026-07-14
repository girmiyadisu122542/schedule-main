<?php

use App\Models\Role\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(Role::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->jsonb('name');
            $table->boolean('unique_per_user');
            $table->boolean('is_system')->default(false); // To check if the role is predefined or newly created
            $table->unsignedSmallInteger('state');
            $table->jsonb('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(Role::getTableName());
    }
};
