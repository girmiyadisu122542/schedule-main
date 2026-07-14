<?php

use App\Models\Role\Role;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(UserRoleBinding::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->smallInteger('not_deleted')->nullable()->storedAs("CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END");

            $table->softDeletes();
            $table->timestamps();

            $table->unique(USER_ROLE_UNIQUE_COLUMN, USER_ROLE_UNIQUE_KEY);

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('role_id')->constrained(Role::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(UserRoleBinding::getTableName());
    }
};
