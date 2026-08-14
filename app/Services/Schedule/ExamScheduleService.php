<?php

namespace App\Services\Schedule;

use App\Models\Offering\CourseOffering;
use App\Models\Physical\Room;
use App\Models\Schedule\ExamSchedule;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamScheduleService {

    /**
     * Which conflict constraint maps to which error key. Clash detection is the
     * database's job (two EXCLUDE constraints); this service's job is to turn
     * the resulting QueryException back into something a user can read.
     *
     * @var array<string, string>
     */
    public const CONFLICT_KEYS = [
        'es_no_room_clash' => 'exam_room_time_conflict',
        'es_no_section_clash' => 'exam_section_time_conflict',
        // A PREFIX, not the full constraint name: PostgreSQL truncates
        // identifiers at 63 characters, so the generated
        // `exam_schedules_course_offering_id_exam_type_lookup_value_id_unique`
        // actually lands as `..._value_id_uni` and a full-name match never fires.
        'exam_schedules_course_offering_id_exam_type' => 'exam_already_scheduled_for_offering',
    ];

    /**
     * Place one exam sitting by hand. It always starts at
     * EXAM_SCHEDULE_STATUS `draft`.
     *
     * @param array $data validated request payload
     * @return \App\Models\Schedule\ExamSchedule|string
     */
    public function createSchedule(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $offering = CourseOffering::with('status')->find((int) $data['course_offering_id']);
        if (!$offering) {
            return 'course_offering_not_found';
        }

        $guard = $this->guardInputs($offering, $data);
        if ($guard !== null) {
            return $guard;
        }

        $draftId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_DRAFT, needId: true);
        if (!$draftId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($offering, $data);
            $attributes['status_lookup_value_id'] = $draftId;
            $attributes['state'] = STATE_ACTIVE;
            $attributes['created_by_id'] = Auth::id();

            $schedule = ExamSchedule::create($attributes);

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
     * Adjust a sitting: move its date, window or room. Only a draft may be
     * adjusted — once the department is looking at it, its content is what they
     * are agreeing to.
     *
     * @param \App\Models\Schedule\ExamSchedule $schedule
     * @param array $data validated request payload
     *
     * @return \App\Models\Schedule\ExamSchedule|string
     */
    public function updateSchedule(ExamSchedule $schedule, array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        if (!$schedule->isDraft()) {
            return 'only_draft_exams_can_be_edited';
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
     * Send a draft to the department, or record their decision.
     *
     * The endpoint is one action because both halves are the same guarded move
     * along EXAM_SCHEDULE_STATUS: `draft → pending_confirmation` when the
     * registrar asks, `pending_confirmation → confirmed` when the department
     * agrees. Which one happens is decided by where the sitting already is, not
     * by a flag the caller passes.
     *
     * @param \App\Models\Schedule\ExamSchedule $schedule
     * @param string|null $remark the department's note, when they are confirming
     *
     * @return \App\Models\Schedule\ExamSchedule|string
     */
    public function confirm(ExamSchedule $schedule, ?string $remark = null) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        $targetCode = $currentCode === EXAM_SCHEDULE_STATUS_DRAFT
            ? EXAM_SCHEDULE_STATUS_PENDING_CONFIRMATION
            : EXAM_SCHEDULE_STATUS_CONFIRMED;

        if (!LookupService::isTransitionAllowed(EXAM_SCHEDULE_STATUS, $currentCode, $targetCode)) {
            return 'invalid_status_transition';
        }

        $targetId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, $targetCode, needId: true);
        if (!$targetId) {
            return 'status_lookup_value_not_found';
        }

        $isConfirmation = $targetCode === EXAM_SCHEDULE_STATUS_CONFIRMED;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $schedule->status_lookup_value_id = $targetId;

            // Only the department's own decision stamps the actor — asking for
            // it is the registrar's move, and records nobody.
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

        return $schedule->refresh();
    }

    /**
     * Publish a sitting. Legal from `draft` (nothing needed signing off) and
     * from `confirmed` — `lookup_transitions` declares both, and refuses
     * publishing something still `pending_confirmation`.
     *
     * @param \App\Models\Schedule\ExamSchedule $schedule
     * @return \App\Models\Schedule\ExamSchedule|string
     */
    public function publish(ExamSchedule $schedule) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(EXAM_SCHEDULE_STATUS, $currentCode, EXAM_SCHEDULE_STATUS_PUBLISHED)) {
            return 'invalid_status_transition';
        }

        // A sitting with no hall is not something a student can turn up to.
        if (!$schedule->room_id) {
            return 'exam_needs_a_room';
        }

        $publishedId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_PUBLISHED, needId: true);
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
     * Cancel a sitting: `status -> cancelled` AND `state -> STATE_INACTIVE` in
     * one write, which frees the hall and the cohort's window.
     *
     * @param \App\Models\Schedule\ExamSchedule $schedule
     * @return \App\Models\Schedule\ExamSchedule|string
     */
    public function cancel(ExamSchedule $schedule) {
        // ---- pre-flight checks (NO writes yet) ----
        $currentCode = $schedule->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if (!LookupService::isTransitionAllowed(EXAM_SCHEDULE_STATUS, $currentCode, EXAM_SCHEDULE_STATUS_CANCELLED)) {
            return 'invalid_status_transition';
        }

        $cancelledId = LookupService::getValueByCode(EXAM_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS_CANCELLED, needId: true);
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
     * @param \App\Models\Offering\CourseOffering $offering the sitting's parent
     * @param array $data validated request payload
     *
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(CourseOffering $offering, array $data): ?string {
        if ($offering->status?->code !== COURSE_OFFERING_STATUS_REGISTRAR_APPROVED) {
            return 'offering_is_not_approved';
        }

        if (!empty($data['room_id'])) {
            $room = Room::find((int) $data['room_id']);
            if (!$room?->is_active) {
                return 'room_is_not_active';
            }

            if (!$room->is_exam_venue) {
                return 'room_is_not_an_exam_venue';
            }

            // Spaced seating, not teaching capacity — a hall that seats 60 for a
            // lecture seats far fewer candidates a desk apart.
            $seats = $room->exam_capacity ?? $room->capacity;
            if ($seats < $offering->expected_students) {
                return 'exam_room_capacity_is_too_small';
            }
        }

        return null;
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * `semester_id` and `section_id` are mirrored off the offering, never taken
     * from the payload — the composite foreign keys would reject any other
     * value, and the EXCLUDE constraints read them off this row.
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
            'exam_type_lookup_value_id' => (int) $data['exam_type_lookup_value_id'],
            'exam_date' => $data['exam_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room_id' => $data['room_id'] ?? null,
            'required_invigilators' => (int) ($data['required_invigilators'] ?? 1),
            // Accommodations (C21). Kept nullable throughout: most sittings
            // need none, and an empty string stored as "no note" would make
            // "has an accommodation" impossible to query for.
            'accommodation_note' => $data['accommodation_note'] ?? null,
            'accommodation_extra_minutes' => isset($data['accommodation_extra_minutes'])
                ? (int) $data['accommodation_extra_minutes']
                : null,
            'accommodation_room_id' => $data['accommodation_room_id'] ?? null,
        ];
    }
}
