<?php

use App\Models\Common\Lookup\LookupType;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(LookupType::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->string('code')->unique();
            $table->jsonb('applies_to_model');
            $table->jsonb('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);

            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists(LookupType::getTableName());
    }
};
