<?php

use App\Models\Academic\College;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §3 colleges.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(College::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 20)->unique();
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Routing pointer for the college-approval step — nullable because a
            // college can sit vacant between deans. Authorization stays RBAC.
            $table->foreignId('dean_user_id')->nullable()->constrained(User::getTableName())->nullOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(College::getTableName());
    }
};
