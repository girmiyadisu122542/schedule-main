<?php

namespace App\Models\Invigilation;

use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registrar's ask for invigilators, covering one examination scope.
 *
 * The scope is a semester plus an exam type — what an institution calls "the
 * mid-semester examination". Quantities are per department and live on
 * `InvigilationRequestDepartment`.
 */
class InvigilationRequest extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * Without the datetime cast `sent_at` comes back as a raw string and the
     * resource's `->format(...)` fatals on it.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
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
        'status_lookup_value_id',
        'requested_by_id',
        'remark',
        'sent_at',
    ];

    /**
     * Relationship Semester — the examination scope.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Relationship LookupValue — the EXAM_TYPE this request covers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function examType(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'exam_type_lookup_value_id');
    }

    /**
     * Relationship LookupValue — INVIGILATION_REQUEST_STATUS.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    /**
     * Relationship User — the registrar who raised it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function requestedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * Relationship InvigilationRequestDepartment — one share per department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function departments(): HasMany {
        return $this->hasMany(InvigilationRequestDepartment::class);
    }

    /**
     * How this request reads in a list or a confirm dialog.
     *
     * @return string
     */
    public function displayLabel(): string {
        return trim(implode(' · ', array_filter([
            $this->examType?->name__localized,
            $this->semester?->name__localized ?? $this->semester?->displayLabel(),
        ])));
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * The three fulfilment figures are DERIVED here rather than stored: they
     * are `required_count` summed against the submissions actually on record,
     * and a stored copy would drift the first time somebody withdraws.
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
            Field::statusLookupValueId()->asInt(),
            Field::remark(),
            Field::statusCode('status.code'),
            Field::examTypeCode('examType.code'),
            Field::make('required_total', fn ($data) => (int) $data->departments->sum('required_count')),
            Field::make('submitted_total', fn ($data) => (int) $data->departments->sum(fn ($share) => $share->submissions->count())),
            Field::make('remaining_total', fn ($data) => max(0, (int) $data->departments->sum('required_count') - (int) $data->departments->sum(fn ($share) => $share->submissions->count()))),
            Field::make('department_count', fn ($data) => $data->departments->count()),
            Field::makeResource('semester', fields: 'idAndNameFields'),
            Field::makeResource('exam_type', 'examType', fields: 'idAndNameFields'),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('requested_by', 'requestedBy', fields: 'idAndNameFields'),
            Field::makeCollection('departments', fields: 'indexFields'),
            Field::sentAt(fn ($data) => $data->sent_at?->format(DATETIME_FORMAT)),
            Field::createdAt(fn ($data) => $data->created_at->format(DATE_FORMAT)),
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
        ];
    }
}
