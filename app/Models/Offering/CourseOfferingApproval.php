<?php

namespace App\Models\Offering;

use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decision in an offering's four-tier approval trail.
 *
 * Append-only: a reversal is a new row, never an edit — hence no `updated_at`
 * and no soft delete (Final Schema.md §13).
 */
class CourseOfferingApproval extends ScopedModel {

    /**
     * Append-only: Eloquent must not try to maintain an `updated_at` column
     * this table deliberately does not have.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'acted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_offering_id',
        'level_lookup_value_id',
        'decision_lookup_value_id',
        'sequence',
        'acted_by_id',
        'acted_at',
        'remark',
        'created_at',
    ];

    /**
     * Relationship CourseOffering
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function courseOffering(): BelongsTo {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * Relationship LookupValue — which tier acted (APPROVAL_LEVEL).
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function level(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'level_lookup_value_id');
    }

    /**
     * Relationship LookupValue — what the tier decided (APPROVAL_DECISION).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function decision(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'decision_lookup_value_id');
    }

    /**
     * Relationship User — who acted.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'acted_by_id');
    }

    /**
     * Fields returned by the trail endpoints.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::sequence()->asInt(),
            Field::remark(),
            Field::courseOfferingId()->asInt(),
            // The badges read the codes + the lookups' own colours.
            Field::levelCode('level.code'),
            Field::decisionCode('decision.code'),
            Field::makeResource('level', fields: 'idAndNameFields'),
            Field::makeResource('decision', fields: 'idAndNameFields'),
            Field::makeResource('actor', fields: 'idAndNameFields'),
            Field::actedAt(fn ($data) => $data->acted_at?->format(DATETIME_FORMAT)),
        ];
    }

    /**
     * Compact shape used by embedded resources.
     *
     * @return array
     */
    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::sequence()->asInt(),
            Field::levelCode('level.code'),
            Field::decisionCode('decision.code'),
        ];
    }
}
