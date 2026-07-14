<?php

use App\Models\Permission\Permission;
use App\Models\Permission\PermissionGroup;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(Permission::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->string('key')->unique();
            $table->boolean('unique_per_user');
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('state');
            $table->jsonb('allowed_roles')->nullable();
            $table->timestamps();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('permission_group_id')->constrained(PermissionGroup::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(Permission::getTableName());
    }
};
