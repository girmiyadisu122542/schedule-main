<?php

namespace App\Models\Physical;

use App\Models\Academic\Department;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use App\Models\User;
use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends ScopedModel {
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'is_exam_venue' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'building_id',
        // The owning department, or null for a room nobody has been given yet.
        // Assigned from the DEPARTMENT side (DepartmentService::syncRooms), so
        // that "these are our rooms" is one decision in one screen.
        'department_id',
        'floor',
        'room_type_lookup_value_id',
        'capacity',
        'exam_capacity',
        'is_exam_venue',
        'is_active',
        'user_id',
    ];

    /**
     * Relationship Building
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function building(): BelongsTo {
        return $this->belongsTo(Building::class);
    }

    /**
     * Relationship Department — the room's owner.
     *
     * Null means unassigned, which is not the same as "available to everyone":
     * only the owning department may schedule into a room, so an unassigned
     * room is scheduled by nobody until somebody is given it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department(): BelongsTo {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship Campus — a room's full location reads
     * "NB-301, New Block, Main Campus", and the campus is one hop past the building.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function campus(): HasOneThrough {
        return $this->hasOneThrough(
            Campus::class,
            Building::class,
            'id',
            'id',
            'building_id',
            'campus_id',
        );
    }

    /**
     * Relationship LookupValue — the ROOM_TYPE this room is built for.
     * A semantic relation, so it keeps its explicit FK argument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function roomType(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'room_type_lookup_value_id');
    }

    /**
     * Relationship User — the record creator.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship ClassSchedule — the meetings booked in this room (Final Schema.md §9).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classSchedules(): HasMany {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * Relationship ExamSchedule — the sittings booked in this hall.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function examSchedules(): HasMany {
        return $this->hasMany(ExamSchedule::class);
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
            Field::code(),
            Field::name('name__localized'),
            Field::buildingId()->asInt(),
            Field::departmentId()->asInt(),
            Field::roomTypeLookupValueId()->asInt(),
            Field::floor()->asInt(),
            Field::capacity()->asInt(),
            Field::examCapacity()->asInt(),
            Field::isExamVenue()->asBool(),
            Field::isActive()->asBool(),
            // The type chip reads `room_type_code` + the lookup's own colour.
            Field::roomTypeCode('roomType.code'),
            Field::makeResource('room_type', 'roomType', fields: 'idAndNameFields'),
            Field::makeResource('building', fields: 'idAndNameFields'),
            Field::makeResource('department', fields: 'idAndNameFields'),
            Field::makeResource('campus', fields: 'idAndNameFields'),
            Field::makeResource('created_by', 'user', fields: 'idAndNameFields'),
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
            Field::code(),
            // A room's code IS its name on a timetable ("NB-301").
            Field::name(fn ($data) => $data->name__localized ?: $data->code),
        ];
    }
}
