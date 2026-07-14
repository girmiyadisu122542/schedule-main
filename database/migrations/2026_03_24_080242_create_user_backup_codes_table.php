<?php

use App\Models\User;
use App\Models\User\User2FA;
use App\Models\User\UserBackupCode;
use App\Models\User\UserOTPCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(UserBackupCode::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->unsignedSmallInteger('status')->default(USER_BACKUP_CODE_NEW);

            $table->softDeletes();
            $table->timestamps();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create(UserOTPCode::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreignId('user2_f_a_id')->constrained(User2FA::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(UserBackupCode::getTableName());
        Schema::dropIfExists(UserOTPCode::getTableName());
    }
};
