<?php

use App\Models\Permission\Permission;
use App\Models\Role\Role;
use App\Models\Role\RolePermission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(RolePermission::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('state');
            $table
                ->smallInteger('not_deleted')
                ->nullable()
                ->storedAs("CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END");

            $table->softDeletes();
            $table->timestamps();

            $table->unique(ROLE_PERMISSION_UNIQUE_COLUMN, 'role_permission_unique');

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('role_id')->constrained(Role::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained(Permission::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(RolePermission::getTableName());
    }
};
