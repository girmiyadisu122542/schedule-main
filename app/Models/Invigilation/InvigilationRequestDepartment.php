<?php

namespace App\Models\Invigilation;

use App\Models\Academic\Department;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One department's share of one invigilation request.
 *
 * The reason this table exists: quantities differ per department. Asking
 * Computer Science for ten and Accounting for four is one request carrying two
 * numbers, which a single column on the request could not hold.
 */
class InvigilationRequestDepartment extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'required_count' => 'integer',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invigilation_request_id',
        'department_id',
        'required_count',
    ];

    /**
     * Relationship InvigilationRequest — the ask this share belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function request(): BelongsTo {
        return $this->belongsTo(InvigilationRequest::class, 'invigilation_request_id');
    }

    /**
     * Relationship Department — who is being asked.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship InvigilationSubmission — the people offered so far.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function submissions(): HasMany {
        return $this->hasMany(InvigilationSubmission::class, 'invigilation_request_department_id');
    }

    /** How many people this department has offered. */
    public function submittedCount(): int {
        return $this->submissions->count();
    }

    /** How many it still owes. Never negative — over-submission is refused. */
    public function remainingCount(): int {
        return max(0, $this->required_count - $this->submittedCount());
    }

    /**
     * Where this share has got to, derived from the two counts.
     *
     * Not a stored column and not a lookup value: it is entirely a function of
     * `required_count` and the submissions on record, so storing it would be a
     * second copy of the same fact.
     *
     * @return string one of `pending`, `partial`, `complete`
     */
    public function fulfilmentCode(): string {
        $submitted = $this->submittedCount();

        if ($submitted === 0) {
            return 'pending';
        }

        return $submitted >= $this->required_count ? 'complete' : 'partial';
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::invigilationRequestId()->asInt(),
            Field::departmentId()->asInt(),
            Field::requiredCount()->asInt(),
            Field::make('submitted_count', fn ($data) => $data->submittedCount()),
            Field::make('remaining_count', fn ($data) => $data->remainingCount()),
            Field::make('fulfilment_code', fn ($data) => $data->fulfilmentCode()),
            Field::makeResource('department', fields: 'idAndNameFields'),
            Field::makeResource('request', fields: 'idAndNameFields'),
            Field::makeCollection('submissions', fields: 'indexFields'),
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
            Field::departmentId()->asInt(),
            Field::requiredCount()->asInt(),
            Field::name('department.name__localized'),
        ];
    }
}
