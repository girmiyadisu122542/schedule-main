<?php

namespace App\Services\Schedule;

use App\Constants\ScheduleConstant;
use App\Models\Schedule\ClassSchedule;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\English;

class ClassScheduleService {

    /**
     * Create a class / exam schedule entry.
     *
     * @param array $data validated request payload
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function createSchedule(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if ($this->hasTimeConflict($data)) {
            return 'schedule_time_conflict';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['uuid'] = (string) Str::uuid();
            $attributes['code'] = generateCode(name: $data['name'], options: [
                CODE_OPT_UNIQUE => true,
                CODE_OPT_MODEL => ClassSchedule::class,
            ]);
            $attributes['user_id'] = Auth::id();
            $attributes['state'] = $data['state'] ?? STATE_ACTIVE;

            $schedule = ClassSchedule::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule;
    }

    /**
     * Update a schedule entry.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @param array $data validated request payload
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function updateSchedule(ClassSchedule $schedule, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if ($this->hasTimeConflict($data, ignoreId: $schedule->id)) {
            return 'schedule_time_conflict';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->fill($this->buildAttributes($data));
            if (isset($data['state'])) {
                $schedule->state = (int) $data['state'];
            }
            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule->refresh();
    }

    /**
     * Map a validated payload onto model attributes. The localized name keeps
     * the {"en": ...} jsonb shape used across the project.
     *
     * @param array $data validated request payload
     * @return array
     */
    private function buildAttributes(array $data): array {
        $isExam = (int) $data['schedule_type'] === ScheduleConstant::TYPE_EXAM;

        return [
            'name' => [English::getKey() => $data['name']],
            'schedule_type' => (int) $data['schedule_type'],
            'day_of_week' => $isExam ? null : ($data['day_of_week'] ?? null),
            'exam_date' => $isExam ? ($data['exam_date'] ?? null) : null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room' => $data['room'] ?? null,
            'section' => $data['section'] ?? null,
        ];
    }

    /**
     * A schedule conflicts when another ACTIVE entry books the same room on
     * the same day (class) or date (exam) with an overlapping time range.
     * Entries without a room never conflict.
     *
     * @param array $data validated request payload
     * @param int|null $ignoreId schedule id to exclude (update case)
     * @return bool
     */
    private function hasTimeConflict(array $data, ?int $ignoreId = null): bool {
        $room = $data['room'] ?? null;
        if (!$room) {
            return false;
        }

        $isExam = (int) $data['schedule_type'] === ScheduleConstant::TYPE_EXAM;

        return ClassSchedule::query()
            ->where('room', $room)
            ->where('state', STATE_ACTIVE)
            ->when($ignoreId, fn ($query) => $query->whereNot('id', $ignoreId))
            ->when($isExam, fn ($query) => $query->where('exam_date', $data['exam_date'] ?? null))
            ->when(!$isExam, fn ($query) => $query->where('day_of_week', $data['day_of_week'] ?? null))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();
    }
}
