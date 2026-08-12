<?php

use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use App\Models\People\Instructor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Promote `instructors.academic_rank` from a free-form string to a lookup FK.
     *
     * Final Schema.md §11 originally kept the rank a plain `string(40)` and named
     * it "a candidate for a lookup type if it ever drives behaviour". Spreadsheet
     * import is that moment: a sheet a registrar fills in must carry a stable
     * `academic_rank_code` the importer can resolve, not free text that drifts
     * ("Asst. Prof", "assistant professor", "Assistant Prof.") into unqueryable
     * noise.
     *
     * The column stays nullable — the ladder does not describe every person on
     * the payroll, and §11 already allowed a rankless instructor.
     *
     * @return void
     */
    public function up(): void {
        Schema::table(Instructor::getTableName(), function (Blueprint $table) {
            $table->foreignId('academic_rank_lookup_value_id')
                ->nullable()
                ->after('phone')
                ->constrained(LookupValue::getTableName())
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });

        $this->backfillFromLegacyStrings();

        Schema::table(Instructor::getTableName(), function (Blueprint $table) {
            $table->dropColumn('academic_rank');
        });
    }

    /**
     * Match each legacy free-text rank against the seeded ACADEMIC_RANK values.
     *
     * Compares case-insensitively against both the stable `code` and the English
     * label, with spaces and hyphens folded to underscores, so "Assistant
     * Professor", "assistant-professor" and "assistant_professor" all land on the
     * same value. Anything unrecognised is left null rather than guessed at — a
     * wrong rank is worse than a missing one, and the row is still importable.
     *
     * @return void
     */
    private function backfillFromLegacyStrings(): void {
        if (!Schema::hasColumn(Instructor::getTableName(), 'academic_rank')) {
            return;
        }

        $lookupTypeId = LookupType::query()->where('code', ACADEMIC_RANK)->value('id');
        if (!$lookupTypeId) {
            return;
        }

        $values = LookupValue::query()->where('lookup_type_id', $lookupTypeId)->get();

        $normalize = fn (string $value): string => strtolower(str_replace([' ', '-', '.'], ['_', '_', ''], trim($value)));

        foreach ($values as $value) {
            $englishName = is_array($value->name) ? ($value->name['en'] ?? '') : '';

            $candidates = array_unique(array_filter([
                $normalize((string) $value->code),
                $englishName ? $normalize($englishName) : null,
            ]));

            DB::table(Instructor::getTableName())
                ->whereNotNull('academic_rank')
                ->whereIn(DB::raw("lower(replace(replace(replace(trim(academic_rank), ' ', '_'), '-', '_'), '.', ''))"), $candidates)
                ->update(['academic_rank_lookup_value_id' => $value->id]);
        }
    }

    /**
     * Reverse the migrations. Restores the string column and writes back the
     * English label of whatever lookup value the row points at.
     *
     * @return void
     */
    public function down(): void {
        Schema::table(Instructor::getTableName(), function (Blueprint $table) {
            $table->string('academic_rank', MAX_ACADEMIC_RANK_LENGTH)->nullable()->after('phone');
        });

        $instructors = DB::table(Instructor::getTableName())
            ->whereNotNull('academic_rank_lookup_value_id')
            ->get(['id', 'academic_rank_lookup_value_id']);

        foreach ($instructors as $instructor) {
            $value = LookupValue::find($instructor->academic_rank_lookup_value_id);
            $englishName = is_array($value?->name) ? ($value->name['en'] ?? null) : null;

            DB::table(Instructor::getTableName())
                ->where('id', $instructor->id)
                ->update(['academic_rank' => $englishName ?? $value?->code]);
        }

        Schema::table(Instructor::getTableName(), function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_rank_lookup_value_id');
        });
    }
};
