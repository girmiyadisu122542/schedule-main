<?php

namespace App\Models\Schedule;

use App\Models\Academic\Semester;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One automatic-scheduling execution. Telemetry, not timetable data — it makes
 * a run inspectable ("placed 6, could not place 1") and gives the progress UI
 * something to poll.
 */
class ScheduleGenerationRun extends ScopedModel {

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'summary' => 'array',
        'snapshot' => 'array',
        'is_dry_run' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'semester_id',
        'type_lookup_value_id',
        'status_lookup_value_id',
        'scheduled_count',
        'unplaced_count',
        'duration_seconds',
        'summary',
        'snapshot',
        'is_dry_run',
        'run_by_id',
        'started_at',
        'completed_at',
    ];

    /**
     * Relationship Semester
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Relationship LookupValue — class run or exam run. A semantic relation, so
     * it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'type_lookup_value_id');
    }

    /**
     * Relationship LookupValue — running / completed / failed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'status_lookup_value_id');
    }

    /**
     * Relationship User — who triggered the run.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function runBy(): BelongsTo {
        return $this->belongsTo(User::class, 'run_by_id');
    }

    /**
     * Relationship ClassSchedule — the meetings this run produced.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classSchedules(): HasMany {
        return $this->hasMany(ClassSchedule::class, 'generation_run_id');
    }

    /**
     * A readable label: "Class generation — Semester 1, 2018/19".
     *
     * @return string
     */
    public function displayLabel(): string {
        $type = $this->type?->name__localized;
        $semester = $this->semester?->name__localized;

        return trim(implode(' — ', array_filter([$type, $semester])));
    }

    /**
     * Relationship ExamSchedule — the sittings this run produced (Final Schema.md §16).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function examSchedules(): HasMany {
        return $this->hasMany(ExamSchedule::class, 'generation_run_id');
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
            Field::scheduledCount()->asInt(),
            Field::unplacedCount()->asInt(),
            Field::durationSeconds()->asInt(),
            // The chip reads `status_code` + the lookup's own colour.
            Field::statusCode('status.code'),
            Field::typeCode('type.code'),
            Field::makeResource('status', fields: 'idAndNameFields'),
            Field::makeResource('type', fields: 'idAndNameFields'),
            Field::makeResource('semester', fields: 'idAndNameFields'),
            Field::makeResource('run_by', 'runBy', fields: 'idAndNameFields'),
            // The per-offering detail the progress UI lists; already an array.
            Field::summary(fn ($data) => $data->summary ?? []),
            // Whether there is anything to restore. The snapshot itself is a
            // whole timetable and has no business in a list payload — only
            // the fact that it exists does.
            Field::isDryRun()->asBool(),
            Field::make('has_snapshot', fn ($data) => !empty($data->snapshot['rows'] ?? [])),
            Field::startedAt(fn ($data) => $data->started_at?->format(DATETIME_FORMAT)),
            Field::completedAt(fn ($data) => $data->completed_at?->format(DATETIME_FORMAT)),
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
