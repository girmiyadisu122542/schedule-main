<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * One-time PostgreSQL setup for the scheduling conflict engine.
     *
     * `btree_gist` lets a GiST EXCLUDE constraint mix equality columns
     * (semester_id, room_id, day_of_week) with a range overlap operator.
     * `timerange` is the range type the class-schedule constraints overlap
     * on — PostgreSQL ships int/num/ts/date ranges but no time range.
     *
     * @return void
     */
    public function up(): void {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // CREATE TYPE has no IF NOT EXISTS clause — guard on the catalogue.
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'timerange') THEN
                    CREATE TYPE timerange AS RANGE (subtype = time);
                END IF;
            END
            $$;
        SQL);
    }

    /**
     * Reverse the migrations. The extension is intentionally left in place —
     * dropping it could break unrelated objects in the same database.
     *
     * @return void
     */
    public function down(): void {
        DB::statement('DROP TYPE IF EXISTS timerange');
    }
};
