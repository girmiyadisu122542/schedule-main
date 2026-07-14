<?php

use App\Models\Permission\Permission;
use App\Models\Role\UserPermissionOverride;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(UserPermissionOverride::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->boolean('allow');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->smallInteger('not_deleted')->nullable()->storedAs("CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END");

            $table->softDeletes();
            $table->timestamps();

            $table->unique(USER_PERMISSION_UNIQUE_COLUMN, USER_PERMISSION_UNIQUE_KEY);

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained(Permission::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(UserPermissionOverride::getTableName());
    }
};
