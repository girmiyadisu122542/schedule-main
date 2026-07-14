<?php

use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table(LookupType::getTableName(), function (Blueprint $table) {
            $table->foreignId('status_lookup_value_id')
                ->nullable()
                ->constrained(LookupValue::getTableName())
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table(LookupType::getTableName(), function (Blueprint $table) {
            $table->dropForeign(['status_lookup_value_id']);
            $table->dropColumn('status_lookup_value_id');
        });
    }
};
