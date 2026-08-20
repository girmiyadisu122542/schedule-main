<?php

namespace App\Services\Schedule;

use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Translation\Message;

class ClassScheduleService {

    /**
     * Which conflict constraint maps to which error key.
     *
     * Clash detection used to be entirely the database's job — three GiST
     * EXCLUDE constraints. MySQL cannot express those, so
     * {@see ScheduleConflictGuard::classSchedule()} now decides, and this map
     * survives for the constraints MySQL still enforces itself (the composite
     * uniques and foreign keys), whose violations still arrive as a
     * QueryException.
     *
     * @var array<string, string>
     */
    public const CONFLICT_KEYS = [
        'cs_no_instructor_clash' => 'instructor_time_conflict',
        'cs_no_room_clash' => 'room_time_conflict',
        'cs_no_section_clash' => 'section_time_conflict',
    ];

    /**
     * Place one class meeting by hand. It always starts at
     * CLASS_SCHEDULE_STATUS `draft` — the status is a guarded lifecycle, never
     * a caller-supplied field.
     *
     * @param array $data validated request payload
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function createSchedule(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $offering = CourseOffering::with('status')->find((int) $data['course_offering_id']);
        if (!$offering) {
            return 'course_offering_not_found';
        }

        $guard = $this->guardInputs($offering, $data, isCreate: true);
        if ($guard !== null) {
            return $guard;
        }

        $draftId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_DRAFT, needId: true);
        if (!$draftId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($offering, $data);
            $attributes['status_lookup_value_id'] = $draftId;
            $attributes['state'] = STATE_ACTIVE;
            $attributes['created_by_id'] = Auth::id();

            // Inside the transaction, before the write: the guard's locking read
            // only holds the slot for as long as this transaction does.
            $conflict = ScheduleConflictGuard::classSchedule($attributes);
            if ($conflict !== null) {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                return $conflict;
            }

            $schedule = ClassSchedule::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $conflict = static::conflictKey($exception);
            if (!$conflict) {
                throw $exception;
            }

            return $conflict;
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule;
    }

    /**
     * Place SEVERAL meetings for one course in a single action.
     *
     * What Create actually means for a coordinator: a course meets more than
     * once a week, so the dialog collects every day and time at once instead of
     * being reopened per sitting with the same course, room and instructor
     * re-picked each time.
     *
     * Each slot is written in its OWN transaction, deliberately. A batch is not
     * all-or-nothing: if the Wednesday slot clashes with something, the Monday
     * and Friday ones are still worth having, and the response says which slot
     * did not land and why. Rolling the lot back would make one clash cost the
     * whole batch.
     *
     * Every slot goes through the same `createSchedule()` the single path uses,
     * so approval, room ownership, instructor capability, the weekly-load cap
     * and clash detection all apply per slot — a batch cannot place anything a
     * single create would refuse.
     *
     * @param array $data validated request payload, carrying `slots`
     *
     * @return array{created: array<int, \App\Models\Schedule\ClassSchedule>, failed: array<int, array>}
     */
    public function createSchedules(array $data): array {
        $shared = [
            'course_offering_id' => $data['course_offering_id'],
            'instructor_id' => $data['instructor_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'session_type_lookup_value_id' => $data['session_type_lookup_value_id'] ?? null,
        ];

        $created = [];
        $failed = [];

        foreach (array_values($data['slots'] ?? []) as $index => $slot) {
            // Per-slot values win; the shared ones fill the gaps. `??` rather
            // than array_merge so an explicitly-null slot room still falls back
            // instead of clearing the shared choice.
            $payload = [
                'course_offering_id' => $shared['course_offering_id'],
                'instructor_id' => $slot['instructor_id'] ?? $shared['instructor_id'],
                'room_id' => $slot['room_id'] ?? $shared['room_id'],
                'session_type_lookup_value_id' => $slot['session_type_lookup_value_id']
                    ?? $shared['session_type_lookup_value_id'],
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
            ];

            $result = $this->createSchedule($payload);

            if (is_string($result)) {
                $message = Message::get($result);

                $failed[] = [
                    'index' => $index,
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'reason' => $result,
                    'reason_message' => is_string($message) && $message !== '' ? $message : $result,
                ];

                continue;
            }

            $created[] = $result;
        }

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * Adjust a meeting: move its day, time, room or instructor. Only a draft may
     * be adjusted — a published timetable is what students are reading.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @param array $data validated request payload
     *
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function updateSchedule(ClassSchedule $schedule, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$schedule->isDraft()) {
            return 'only_draft_schedules_can_be_edited';
        }

        $offering = CourseOffering::with('status')->find((int) $data['course_offering_id']);
        if (!$offering) {
            return 'course_offering_not_found';
        }

        $guard = $this->guardInputs($offering, $data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->fill($this->buildAttributes($offering, $data));

            // Only a live row was ever policed by the EXCLUDE constraints, and
            // the row being moved must not clash with itself.
            $conflict = (int) $schedule->state === STATE_ACTIVE
                ? ScheduleConflictGuard::classSchedule($schedule->getAttributes(), $schedule->id)
                : null;
            if ($conflict !== null) {
                DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                return $conflict;
            }

            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $conflict = static::conflictKey($exception);
            if (!$conflict) {
                throw $exception;
            }

            return $conflict;
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule->refresh();
    }

    /**
     * Publish a meeting: `draft -> published`, stamping who published it.
     * `state` is untouched — a published meeting is still live for conflict
     * purposes, and must stay so.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function publish(ClassSchedule $schedule) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(CLASS_SCHEDULE_STATUS, $currentCode, CLASS_SCHEDULE_STATUS_PUBLISHED)) {
            return 'invalid_status_transition';
        }

        // A meeting nobody teaches, in no room, is not a timetable entry a
        // student can act on.
        if (!$schedule->room_id || !$schedule->instructor_id) {
            return 'schedule_needs_a_room_and_an_instructor';
        }

        $publishedId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_PUBLISHED, needId: true);
        if (!$publishedId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->status_lookup_value_id = $publishedId;
            $schedule->published_by_id = Auth::id();
            $schedule->published_at = now();
            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule->refresh();
    }

    /**
     * Cancel a meeting: `status -> cancelled` AND `state -> STATE_INACTIVE` in
     * one write. The second half is what frees the room, instructor and section
     * slot — the three EXCLUDE constraints only see rows with `state = 1`.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function cancel(ClassSchedule $schedule) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(CLASS_SCHEDULE_STATUS, $currentCode, CLASS_SCHEDULE_STATUS_CANCELLED)) {
            return 'invalid_status_transition';
        }

        $cancelledId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_CANCELLED, needId: true);
        if (!$cancelledId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->status_lookup_value_id = $cancelledId;
            $schedule->state = STATE_INACTIVE;
            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule->refresh();
    }

    /**
     * The error key for a conflict the database refused, or null when the
     * QueryException was something else entirely (and must be rethrown).
     *
     * Static and public because the generator service maps its per-item
     * failures through the same table.
     *
     * @param \Illuminate\Database\QueryException $exception
     * @return string|null
     */
    public static function conflictKey(QueryException $exception): ?string {
        foreach (static::CONFLICT_KEYS as $constraint => $key) {
            if (str_contains($exception->getMessage(), $constraint)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param \App\Models\Offering\CourseOffering $offering the meeting's parent
     * @param array $data validated request payload
     *
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(CourseOffering $offering, array $data, bool $isCreate = false): ?string {
        // A timetable is built from approved offerings. Scheduling one that is
        // still in the approval chain would publish a decision nobody made.
        if ($offering->status?->code !== COURSE_OFFERING_STATUS_REGISTRAR_APPROVED) {
            return 'offering_is_not_approved';
        }

        // A course gets the week its catalogue entry declares — no more.
        //
        // Only on CREATE: editing one of the meetings already counted must not
        // trip a limit it is itself part of.
        //
        // Without this, clicking Create repeatedly kept adding sessions to the
        // same course forever. The clash rules never caught it, because a
        // second meeting on a DIFFERENT day is not a clash — it is simply more
        // teaching than the course has. The generator has always respected this
        // figure; hand-placement ignored it, and the two paths disagreed about
        // what a course's week even was.
        if ($isCreate) {
            $allowed = app(CourseWeeklyLoadService::class)->meetingCountFor(
                $offering,
                app(ScheduleSettingService::class)->forOffering($offering),
            );

            // Cancelled meetings are not teaching, so they do not count against
            // the load — the same reading `state` has everywhere else.
            $existing = ClassSchedule::query()
                ->where('course_offering_id', $offering->id)
                ->where('state', STATE_ACTIVE)
                ->count();

            if ($existing >= $allowed) {
                return 'offering_already_has_its_weekly_sessions';
            }
        }

        if (!empty($data['instructor_id'])) {
            $instructor = Instructor::find((int) $data['instructor_id']);
            if (!$instructor?->can_teach || !$instructor->is_active) {
                return 'instructor_cannot_teach';
            }
        }

        if (!empty($data['room_id'])) {
            $room = Room::find((int) $data['room_id']);
            if (!$room?->is_active) {
                return 'room_is_not_active';
            }

            // A department schedules into its own rooms only. Enforced here as
            // well as in the generator so the rule means the same thing however
            // a class gets placed — a hand-placed meeting cannot sit in a room
            // the department was never given.
            if ((int) $room->department_id !== (int) $offering->department_id) {
                return 'room_is_not_owned_by_department';
            }

            if ($room->capacity < $offering->expected_students) {
                return 'room_capacity_is_too_small';
            }
        }

        return null;
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * `semester_id` and `section_id` are mirrored off the offering, never taken
     * from the payload: the composite foreign keys would reject any other value,
     * and the EXCLUDE constraints read them off this row.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @param array $data validated request payload
     *
     * @return array
     */
    private function buildAttributes(CourseOffering $offering, array $data): array {
        return [
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'section_id' => $offering->section_id,
            'instructor_id' => $data['instructor_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'session_type_lookup_value_id' => $data['session_type_lookup_value_id'] ?? null,
            'day_of_week' => (int) $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ];
    }

    /**
     * The department confirmation step (C26).
     *
     * Which move this is depends on where the session already is, not on
     * anything the caller sends: from `draft` it ASKS the department, from
     * `pending_confirmation` it IS the department's answer. Same shape as the
     * exam lifecycle, deliberately — a department that has learnt one has
     * learnt both.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @param string|null $remark
     *
     * @return \App\Models\Schedule\ClassSchedule|string the model, or a translation key
     */
    public function confirm(ClassSchedule $schedule, ?string $remark = null) {
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        $targetCode = $currentCode === CLASS_SCHEDULE_STATUS_DRAFT
            ? CLASS_SCHEDULE_STATUS_PENDING_CONFIRMATION
            : CLASS_SCHEDULE_STATUS_CONFIRMED;

        if (!LookupService::isTransitionAllowed(CLASS_SCHEDULE_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        $targetId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, $targetCode, needId: true);
        if (!$targetId) {
            return 'status_lookup_value_not_found';
        }

        $isConfirmation = $targetCode === CLASS_SCHEDULE_STATUS_CONFIRMED;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->status_lookup_value_id = $targetId;

            // Only the department's own decision stamps the actor — asking for
            // it is the registrar's move and records nobody.
            if ($isConfirmation) {
                $schedule->confirmed_by_id = Auth::id();
                $schedule->confirmed_at = now();
                $schedule->confirmation_remark = $remark;
            }

            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule;
    }

    /**
     * The department disagrees: send it back to draft for rework.
     *
     * Better than leaving it in `pending_confirmation` forever, which is what
     * happens to a workflow with no way to say no.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @param string|null $remark
     *
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    public function returnToDraft(ClassSchedule $schedule, ?string $remark = null) {
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(CLASS_SCHEDULE_STATUS, $currentCode, CLASS_SCHEDULE_STATUS_DRAFT)) {
            return 'invalid_status_transition';
        }

        $draftId = LookupService::getValueByCode(CLASS_SCHEDULE_STATUS, CLASS_SCHEDULE_STATUS_DRAFT, needId: true);
        if (!$draftId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->status_lookup_value_id = $draftId;
            // The remark is why it came back, which is the useful part.
            $schedule->confirmation_remark = $remark;
            $schedule->confirmed_by_id = null;
            $schedule->confirmed_at = null;
            $schedule->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $schedule;
    }
}
