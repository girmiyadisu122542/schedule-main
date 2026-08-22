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

        // One principal campus among the live rows. MySQL has no partial index,
        // so the predicate moves into a generated column that is 1 for exactly
        // the rows the index should police and NULL for every other — and MySQL
        // treats each NULL in a unique index as distinct, so the false and
        // soft-deleted rows never collide.
        DB::statement(
            'ALTER TABLE campuses'
            . ' ADD COLUMN is_main_unique_key TINYINT'
            . ' GENERATED ALWAYS AS (CASE WHEN is_main = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END) STORED'
        );
        DB::statement('CREATE UNIQUE INDEX campuses_is_main_unique ON campuses (is_main_unique_key)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(Campus::getTableName());
    }
};
