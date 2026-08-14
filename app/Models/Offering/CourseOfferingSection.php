<?php

namespace App\Models\Offering;

use App\Models\Academic\Section;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional section attending a cross-listed offering.
 *
 * The offering's own `section_id` is the owner and is never repeated here, so
 * "whose offering is this" still has one answer and department scoping is
 * untouched. These are the other cohorts that sit in the same room at the same
 * hour — counted into the capacity decision, and clash-checked before a slot is
 * accepted.
 */
class CourseOfferingSection extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expected_students' => 'integer',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'course_offering_id',
        'section_id',
        'expected_students',
    ];

    /**
     * Relationship CourseOffering — the offering being shared.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function courseOffering(): BelongsTo {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * Relationship Section — the attending cohort.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function section(): BelongsTo {
        return $this->belongsTo(Section::class);
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::courseOfferingId()->asInt(),
            Field::sectionId()->asInt(),
            Field::expectedStudents()->asInt(),
            Field::makeResource('section', fields: 'idAndNameFields'),
        ];
    }

    /**
     * Compact shape used by dropdowns and embedded resources.
     *
     * @return array
     */
    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::uuid(),
            Field::sectionId()->asInt(),
            Field::expectedStudents()->asInt(),
        ];
    }
}
