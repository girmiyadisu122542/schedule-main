<?php

namespace App\Services\Academic;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\Physical\Room;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DepartmentService {

    /**
     * Create a department.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\Department|string
     */
    public function createDepartment(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->collegeIsActive((int) $data['college_id'])) {
            return 'college_is_not_active';
        }

        if ($this->roomsTakenByAnother($data['room_ids'] ?? null, null)) {
            return 'room_belongs_to_another_department';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['code'] = !empty($data['code'])
                ? $data['code']
                : generateCode(
                    name: $data['name'],
                    format: CODE_FORMAT_ABBR,
                    options: [
                        CODE_OPT_UNIQUE => true,
                        CODE_OPT_MODEL => Department::class,
                    ],
                );
            $attributes['user_id'] = Auth::id();

            $department = Department::create($attributes);
            $this->syncRooms($department, $data);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $department;
    }

    /**
     * Update a department.
     *
     * @param \App\Models\Academic\Department $department
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\Department|string
     */
    public function updateDepartment(Department $department, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$this->collegeIsActive((int) $data['college_id'])) {
            return 'college_is_not_active';
        }

        if ($this->roomsTakenByAnother($data['room_ids'] ?? null, $department->id)) {
            return 'room_belongs_to_another_department';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data, $department);
            if (!empty($data['code'])) {
                $attributes['code'] = $data['code'];
            }

            $department->fill($attributes);
            $department->save();
            $this->syncRooms($department, $data);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $department->refresh();
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @param \App\Models\Academic\Department|null $department the row being updated, if any
     *
     * @return array
     */
    private function buildAttributes(array $data, ?Department $department = null): array {
        $language = getCurrentLanguage(request());

        return [
            'name' => updateLangField($department?->name, $language, $data['name']),
            'college_id' => (int) $data['college_id'],
            'head_user_id' => $data['head_user_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * A department may not hang off a retired college.
     *
     * @param int $collegeId
     * @return bool
     */
    private function collegeIsActive(int $collegeId): bool {
        return College::query()->where('id', $collegeId)->where('is_active', true)->exists();
    }

    /**
     * Whether any of these rooms already belongs to a DIFFERENT department.
     *
     * Ownership is exclusive, so taking a room has to be refused rather than
     * silently granted: the other department may already have a published
     * timetable standing in it, and quietly reassigning the room would leave
     * their classes in a room that is no longer theirs.
     *
     * @param array|null $roomIds the submitted list, or null when none was sent
     * @param int|null $departmentId the department being saved, if it exists yet
     *
     * @return bool
     */
    private function roomsTakenByAnother(?array $roomIds, ?int $departmentId): bool {
        if (empty($roomIds)) {
            return false;
        }

        return Room::query()
            ->whereIn('id', $roomIds)
            ->whereNotNull('department_id')
            ->when($departmentId, fn ($query) => $query->where('department_id', '!=', $departmentId))
            ->exists();
    }

    /**
     * Point the submitted rooms at this department, and release the ones it no
     * longer claims.
     *
     * An ABSENT `room_ids` key means the caller said nothing about rooms, so
     * the current assignment stands — only an explicit (possibly empty) list
     * changes anything. That distinction is what lets an unrelated edit, like
     * renaming the department, leave its rooms alone.
     *
     * @param \App\Models\Academic\Department $department
     * @param array $data validated request payload
     *
     * @return void
     */
    private function syncRooms(Department $department, array $data): void {
        if (!array_key_exists('room_ids', $data)) {
            return;
        }

        $roomIds = collect($data['room_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();

        // Released first: a room being handed to another department in the same
        // save must be free before it is claimed.
        Room::query()
            ->where('department_id', $department->id)
            ->when($roomIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $roomIds))
            ->update(['department_id' => null]);

        if ($roomIds->isNotEmpty()) {
            Room::query()->whereIn('id', $roomIds)->update(['department_id' => $department->id]);
        }
    }
}
