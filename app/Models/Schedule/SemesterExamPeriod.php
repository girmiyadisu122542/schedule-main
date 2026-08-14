<?php

namespace App\Models\Schedule;

use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The declared exam window for one semester and one exam type.
 *
 * The authority on when an exam period runs. Without a row here the generator
 * falls back to the derived window — `exam_period_days` counted back from the
 * semester's end — which is why this table is optional rather than required.
 */
class SemesterExamPeriod extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'semester_id',
        'exam_type_lookup_value_id',
        'start_date',
        'end_date',
        'is_active',
    ];

    /**
     * Relationship Semester — the term this window belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Relationship LookupValue — midterm / final / makeup / quiz.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function examType(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'exam_type_lookup_value_id');
    }

    /**
     * A readable label: "Final · 2026-08-20 → 2026-09-02".
     *
     * @return string
     */
    public function displayLabel(): string {
        $type = $this->examType?->name__localized ?? '';
        $window = ($this->start_date?->format(DATE_FORMAT) ?? '') . ' → ' . ($this->end_date?->format(DATE_FORMAT) ?? '');

        return trim($type . ' · ' . $window, ' ·');
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
            Field::name(fn ($data) => $data->displayLabel()),
            Field::semesterId()->asInt(),
            Field::examTypeLookupValueId()->asInt(),
            Field::examTypeCode('examType.code'),
            Field::startDate(fn ($data) => $data->start_date?->format(DATE_FORMAT)),
            Field::endDate(fn ($data) => $data->end_date?->format(DATE_FORMAT)),
            Field::isActive()->asBool(),
            Field::makeResource('exam_type', 'examType', fields: 'idAndNameFields'),
            Field::makeResource('semester', fields: 'idAndNameFields'),
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
            Field::name(fn ($data) => $data->displayLabel()),
            Field::startDate(fn ($data) => $data->start_date?->format(DATE_FORMAT)),
            Field::endDate(fn ($data) => $data->end_date?->format(DATE_FORMAT)),
        ];
    }
}
