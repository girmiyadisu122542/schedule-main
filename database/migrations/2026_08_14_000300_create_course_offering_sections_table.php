<?php

use App\Models\Academic\Section;
use App\Models\Offering\CourseOffering;
use App\Models\Offering\CourseOfferingSection;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations. The extra sections a cross-listed offering serves.
     *
     * An offering carries one `section_id` — the cohort that owns it. A course
     * taught jointly to two programmes had no way to say so: duplicating the
     * offering put two rows in the same room at the same hour, which the room
     * EXCLUDE correctly rejects, leaving no legal way to express the truth.
     *
     * This table names the ADDITIONAL sections that attend. The owning section
     * stays on the offering itself and is not repeated here, so there is one
     * answer to "whose offering is this" and the department scope keeps working
     * unchanged.
     *
     * The schedule is still a single row in a single room. What the extra
     * sections gain is a clash check: the generator refuses a slot in which any
     * attending section is already busy, which is what the section EXCLUDE does
     * for the owner.
     *
     * @return void
     */
    public function up(): void {
        Schema::create(CourseOfferingSection::getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->unique();
            // Counted into the room-capacity decision alongside the owner's.
            $table->integer('expected_students')->nullable();
            $table->timestamps();

            $table->foreignId('course_offering_id')->constrained(CourseOffering::getTableName())->restrictOnUpdate()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained(Section::getTableName())->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained(User::getTableName())->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['course_offering_id', 'section_id'], 'cos_offering_section_unique');
            $table->index('section_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void {
        Schema::dropIfExists(CourseOfferingSection::getTableName());
    }
};
