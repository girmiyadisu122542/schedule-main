<?php

use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(LookupValue::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->string('code');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);

            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('lookup_type_id')->constrained(LookupType::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('status_lookup_value_id')->nullable()->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['lookup_type_id', 'code'], 'lookup_value_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists(LookupValue::getTableName());
    }
};
