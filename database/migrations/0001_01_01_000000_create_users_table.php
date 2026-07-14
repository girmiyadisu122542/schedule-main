<?php

use App\Models\User;
use App\Models\User\LoginAttempt;
use App\Models\User\UserDetail;
use App\Models\User\UserLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create(UserDetail::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->jsonb('first_name');
            $table->jsonb('middle_name');
            $table->jsonb('last_name');
            $table->date('birth_date');

            $table->string('phone')->unique();
            $table->string('email')->unique();
            $table->string('photo')->nullable();
            $table->jsonb('bio')->nullable();

            $table->unsignedSmallInteger('gender');
            $table->string('national_id')->unique()->nullable();
            $table->timestamps();
        });

        Schema::create(User::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->jsonb('full_name');
            $table->string('phone')->unique();
            $table->string('email')->unique();
            $table->string('photo')->nullable();
            $table->string('cover_photo')->nullable();
            $table->unsignedSmallInteger('state');
            $table->unsignedSmallInteger('status')->default(TYPE_STATUS_PENDING);
            $table->unsignedInteger('login_count')->default(0);
            $table->boolean('mfa_enabled')->default(false);

            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreignId('user_id')->nullable()->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_detail_id')->constrained(UserDetail::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create(UserLog::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->dateTime('login_time')->nullable();
            $table->dateTime('logout_time')->nullable();
            $table->string('ip');
            $table->string('device');
            $table->string('user_agent');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create(LoginAttempt::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('ip');
            $table->string('username');
            $table->string('user_agent');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists(User::getTableName());
        Schema::dropIfExists(UserDetail::getTableName());
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
