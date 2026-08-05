<?php

use App\Models\Physical\Campus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. Final Schema.md §1 campuses.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(Campus::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->string('code', 20)->unique();
            $table->jsonb('name');
            $table->jsonb('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->index('is_active');
        });

        // One principal campus among the live rows — a partial unique index is
        // the only way to say "at most one true" without blocking the false rows.
        DB::statement('CREATE UNIQUE INDEX campuses_is_main_unique ON campuses (is_main) WHERE is_main = true AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS campuses_is_main_unique');
        Schema::dropIfExists(Campus::getTableName());
    }
};
