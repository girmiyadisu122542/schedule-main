<?php

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
        $nonChildParentKey = PERMISSION_GROUP_NON_CHILD_PARENT;

        Schema::create(PermissionGroup::getTableName(), function (Blueprint $table) use ($nonChildParentKey) {
            $table->id();
            $table->jsonb('name');
            $table->boolean('is_group');
            $table->string('code')->unique();
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);
            $table->unsignedInteger('level'); // To identify the current permission group from the tree

            $table
                ->unsignedBigInteger('parent_id')
                ->storedAs("CASE WHEN permission_group_id IS NOT NULL THEN permission_group_id ELSE $nonChildParentKey END");

            $table->timestamps();

            $table->unique(['name', 'parent_id']);

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('permission_group_id')->nullable()->constrained(PermissionGroup::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(PermissionGroup::getTableName());
    }
};
