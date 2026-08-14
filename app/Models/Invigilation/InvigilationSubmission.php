<?php

namespace App\Models\Invigilation;

use App\Models\People\Instructor;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person a department has offered against one request.
 *
 * This is the roster the exam scheduler draws from — an instructor becomes a
 * candidate by having been submitted, not by merely existing in the staff list.
 */
class InvigilationSubmission extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invigilation_request_department_id',
        'instructor_id',
        'submitted_by_id',
        'submitted_at',
        'remark',
    ];

    /**
     * Relationship InvigilationRequestDepartment — the ask being answered.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function requestDepartment(): BelongsTo {
        return $this->belongsTo(InvigilationRequestDepartment::class, 'invigilation_request_department_id');
    }

    /**
     * Relationship Instructor — the person offered.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instructor(): BelongsTo {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Relationship User — who sent them.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function submittedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * Fields returned by the list and detail endpoints.
     *
     * `employee_no` rides along on the instructor resource — it is the
     * institution's existing staff identifier and doubles as the invigilator
     * code, so no second identity field was introduced.
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::invigilationRequestDepartmentId()->asInt(),
            Field::instructorId()->asInt(),
            Field::remark(),
            Field::makeResource('instructor', fields: 'idAndNameFields'),
            Field::makeResource('submitted_by', 'submittedBy', fields: 'idAndNameFields'),
            Field::submittedAt(fn ($data) => $data->submitted_at?->format(DATETIME_FORMAT)),
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
            Field::instructorId()->asInt(),
            Field::name('instructor.full_name__localized'),
        ];
    }
}
