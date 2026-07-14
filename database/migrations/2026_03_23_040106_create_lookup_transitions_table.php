<?php

use App\Models\Common\Lookup\LookupTransition;
use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(LookupTransition::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('state')->default(STATE_ACTIVE);
            $table->timestamps();

            $table->foreignId('lookup_type_id')->constrained(LookupType::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('from_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('to_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['lookup_type_id', 'from_value_id', 'to_value_id'], 'lookup_transition_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists(LookupTransition::getTableName());
    }
};
