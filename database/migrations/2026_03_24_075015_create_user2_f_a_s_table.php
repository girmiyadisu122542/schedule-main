<?php

use App\Models\User;
use App\Models\User\User2FA;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(User2FA::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->text('secret')->nullable();
            $table->unsignedSmallInteger('type');
            $table->unsignedSmallInteger('state')->default(STATE_INACTIVE);
            $table->dateTime('verified_at')->nullable();
            $table->boolean('is_primary')->default(STATE_INACTIVE);
            $table->timestamps();

            $table->unique(['type', 'user_id'], 'user_id_type_unique');
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(User2FA::getTableName());
    }
};
