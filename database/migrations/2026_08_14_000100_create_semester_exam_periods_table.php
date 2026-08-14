<?php

use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Schedule\SemesterExamPeriod;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. The declared exam window, per semester and exam type.
     *
     * The exam period used to be derived: `exam_period_days` counted back from
     * the semester's end date. That cannot express a midterm week in the middle
     * of the semester, and it rarely matched the dates a registrar had already
     * published. A declared window is the authority; the derived one stays as
     * the fallback for a semester nobody has set dates on yet.
     *
     * One row per (semester, exam type) — a semester has a midterm window and a
     * final window, and they are different weeks.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(SemesterExamPeriod::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreignId('semester_id')->constrained(Semester::getTableName())->restrictOnUpdate()->cascadeOnDelete();
            $table->foreignId('exam_type_lookup_value_id')->constrained(LookupValue::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            // One window per type per semester — two would leave the generator
            // with no way to choose.
            $table->unique(['semester_id', 'exam_type_lookup_value_id'], 'sep_semester_type_unique');
        });

        DB::statement('ALTER TABLE semester_exam_periods ADD CONSTRAINT semester_exam_periods_window_check CHECK (end_date >= start_date)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(SemesterExamPeriod::getTableName());
    }
};
