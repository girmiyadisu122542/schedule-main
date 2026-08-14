<?php

namespace App\Services\Invigilation;

use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Models\Invigilation\InvigilationSubmission;
use App\Models\People\Instructor;
use App\Models\Schedule\ExamSchedule;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamInvigilatorAssignmentService {

    /**
     * Which constraint maps to which error key. Double-booking is the
     * database's job (`eia_no_double_booking`); this service's job is to turn
     * the resulting QueryException back into something a user can read.
     *
     * The composite unique catches the same person twice at the SAME exam,
     * which the EXCLUDE would also catch — different sentence, same mistake.
     *
     * @var array<string, string>
     */
    public const CONFLICT_KEYS = [
        'eia_no_double_booking' => 'invigilator_already_assigned',
        'exam_invigilator_assignments_exam_schedule_id_instructor_id' => 'invigilator_already_on_this_exam',
    ];

    /**
     * INVIGILATION_STATUS is not a `lookup_transitions` lifecycle (Final Schema
     * declares transitions only for the four workflow types), so the legal
     * moves live here — one map, checked in one place.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_MOVES = [
        INVIGILATION_STATUS_ASSIGNED => [
            INVIGILATION_STATUS_ACCEPTED,
            INVIGILATION_STATUS_DECLINED,
            INVIGILATION_STATUS_REPLACED,
        ],
        INVIGILATION_STATUS_ACCEPTED => [
            INVIGILATION_STATUS_DECLINED,
            INVIGILATION_STATUS_REPLACED,
        ],
    ];

    /**
     * The statuses that free the invigilator: a duty nobody is doing must not
     * keep blocking `eia_no_double_booking`.
     *
     * @var array<int, string>
     */
    private const RELEASING_STATUSES = [
        INVIGILATION_STATUS_DECLINED,
        INVIGILATION_STATUS_REPLACED,
    ];

    /**
     * Put one instructor on duty at one exam.
     *
     * @param array $data validated request payload
     * @return \App\Models\Invigilation\ExamInvigilatorAssignment|string
     */
    public function assign(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $exam = ExamSchedule::find((int) $data['exam_schedule_id']);
        if (!$exam) {
            return 'exam_schedule_not_found';
        }

        $guard = $this->guardInputs($exam, (int) $data['instructor_id']);
        if ($guard !== null) {
            return $guard;
        }

        $assignedId = LookupService::getValueByCode(INVIGILATION_STATUS, INVIGILATION_STATUS_ASSIGNED, needId: true);
        if (!$assignedId) {
            return 'status_lookup_value_not_found';
        }

        $roleId = !empty($data['role_lookup_value_id'])
            ? (int) $data['role_lookup_value_id']
            : LookupService::getValueByCode(INVIGILATOR_ROLE, INVIGILATOR_ROLE_ASSISTANT, needId: true);

        if (!$roleId) {
            return 'invalid_invigilator_role';
        }

        // The first person on an empty hall is the chief, whatever the caller
        // sent — a hall with two assistants and no chief is not a hall anybody
        // is in charge of.
        $onDuty = ExamInvigilatorAssignment::query()
            ->where('exam_schedule_id', $exam->id)
            ->where('state', STATE_ACTIVE)
            ->count();

        if ($onDuty === 0 && empty($data['role_lookup_value_id'])) {
            $roleId = LookupService::getValueByCode(INVIGILATOR_ROLE, INVIGILATOR_ROLE_CHIEF, needId: true) ?: $roleId;
        }

        $result = $this->writeAssignment($exam, (int) $data['instructor_id'], $roleId, $assignedId, $data['remark'] ?? null);

        // Putting somebody on a hall that is already fully staffed must not
        // leave it reading as over-staffed — the requirement follows the
        // decision, exactly as `remove()` lowers it.
        if (!is_string($result)) {
            $this->raiseRequirement($exam);
        }

        return $result;
    }

    /**
     * Record the instructor's answer to a duty.
     *
     * Accepting leaves `state` at STATE_ACTIVE — the duty still blocks the
     * invigilator's window. Declining sets it to STATE_INACTIVE, which frees
     * them without erasing what happened.
     *
     * @param \App\Models\Invigilation\ExamInvigilatorAssignment $assignment
     * @param string $decisionCode an INVIGILATION_STATUS code
     *
     * @return \App\Models\Invigilation\ExamInvigilatorAssignment|string
     */
    public function respond(ExamInvigilatorAssignment $assignment, string $decisionCode) {
        // ---- pre-flight checks (NO writes yet) ----
        $allowed = [INVIGILATION_STATUS_ACCEPTED, INVIGILATION_STATUS_DECLINED];
        if (!in_array($decisionCode, $allowed, true)) {
            return 'invalid_invigilation_decision';
        }

        $move = $this->guardMove($assignment, $decisionCode);
        if ($move !== null) {
            return $move;
        }

        $targetId = LookupService::getValueByCode(INVIGILATION_STATUS, $decisionCode, needId: true);
        if (!$targetId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $assignment->status_lookup_value_id = $targetId;
            $assignment->state = in_array($decisionCode, self::RELEASING_STATUSES, true)
                ? STATE_INACTIVE
                : STATE_ACTIVE;
            $assignment->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $assignment->refresh();
    }

    /**
     * Swap one invigilator for another on the same duty.
     *
     * Both halves are one transaction: releasing the old row before inserting
     * the new one is what lets the replacement take an overlapping window the
     * outgoing person was holding.
     *
     * @param \App\Models\Invigilation\ExamInvigilatorAssignment $assignment
     * @param int $newInstructorId
     * @param string|null $remark why the swap happened
     *
     * @return \App\Models\Invigilation\ExamInvigilatorAssignment|string
     */
    public function replace(ExamInvigilatorAssignment $assignment, int $newInstructorId, ?string $remark = null) {
        // ---- pre-flight checks (NO writes yet) ----
        $move = $this->guardMove($assignment, INVIGILATION_STATUS_REPLACED);
        if ($move !== null) {
            return $move;
        }

        if ($newInstructorId === $assignment->instructor_id) {
            return 'nothing_is_changed';
        }

        $exam = ExamSchedule::find($assignment->exam_schedule_id);
        if (!$exam) {
            return 'exam_schedule_not_found';
        }

        $guard = $this->guardInputs($exam, $newInstructorId);
        if ($guard !== null) {
            return $guard;
        }

        $replacedId = LookupService::getValueByCode(INVIGILATION_STATUS, INVIGILATION_STATUS_REPLACED, needId: true);
        $assignedId = LookupService::getValueByCode(INVIGILATION_STATUS, INVIGILATION_STATUS_ASSIGNED, needId: true);

        if (!$replacedId || !$assignedId) {
            return 'status_lookup_value_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $assignment->status_lookup_value_id = $replacedId;
            $assignment->state = STATE_INACTIVE;
            $assignment->remark = $remark ?? $assignment->remark;
            $assignment->save();

            $replacement = ExamInvigilatorAssignment::create([
                'exam_schedule_id' => $exam->id,
                'instructor_id' => $newInstructorId,
                'exam_date' => $exam->exam_date,
                'start_time' => $exam->start_time,
                'end_time' => $exam->end_time,
                'role_lookup_value_id' => $assignment->role_lookup_value_id,
                'status_lookup_value_id' => $assignedId,
                'state' => STATE_ACTIVE,
                'assigned_by_id' => Auth::id(),
                'assigned_at' => now(),
                'remark' => $remark,
            ]);

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

        return $replacement;
    }

    /**
     * Fill every published-or-earlier sitting in a semester up to the number of
     * invigilators it asks for.
     *
     * Instructors are drawn from whoever the departments SENT for this
     * examination — the invigilation request workflow is the only source.
     * `eia_no_double_booking` then decides whether each candidate is actually
     * free, exactly as the schedule generators use their EXCLUDE constraints.
     *
     * @param int $semesterId
     * @return array{assigned: int, short: array<int, array<string, mixed>>}|string
     */
    public function autoAssign(int $semesterId) {
        // ---- pre-flight checks (NO writes yet) ----
        $assignedId = LookupService::getValueByCode(INVIGILATION_STATUS, INVIGILATION_STATUS_ASSIGNED, needId: true);
        $chiefId = LookupService::getValueByCode(INVIGILATOR_ROLE, INVIGILATOR_ROLE_CHIEF, needId: true);
        $assistantId = LookupService::getValueByCode(INVIGILATOR_ROLE, INVIGILATOR_ROLE_ASSISTANT, needId: true);

        if (!$assignedId || !$chiefId || !$assistantId) {
            return 'status_lookup_value_not_found';
        }

        $exams = ExamSchedule::query()
            ->with(['courseOffering.course', 'examType'])
            ->where('semester_id', $semesterId)
            ->where('state', STATE_ACTIVE)
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        if ($exams->isEmpty()) {
            return 'no_exams_to_staff';
        }

        $assigned = 0;
        $short = [];

        foreach ($exams as $exam) {
            $onDuty = ExamInvigilatorAssignment::query()
                ->where('exam_schedule_id', $exam->id)
                ->where('state', STATE_ACTIVE)
                ->count();

            $needed = max(0, $exam->required_invigilators - $onDuty);
            if ($needed === 0) {
                continue;
            }

            $candidates = $this->candidatesFor($exam);

            if ($candidates->isEmpty()) {
                $short[] = [
                    'exam_schedule_id' => $exam->id,
                    'label' => $exam->displayLabel(),
                    'required' => $exam->required_invigilators,
                    'on_duty' => $onDuty,
                    // "Nobody was sent" and "everybody was busy" call for
                    // completely different actions — raise a request, or hunt
                    // for a free hall. Saying which is the whole value here.
                    'reason' => 'no_invigilators_submitted',
                ];

                continue;
            }

            $placed = 0;
            foreach ($candidates as $instructorId) {
                if ($placed >= $needed) {
                    break;
                }

                // The first person on a sitting with nobody yet is the chief.
                $roleId = ($onDuty + $placed) === 0 ? $chiefId : $assistantId;

                $result = $this->writeAssignment($exam, $instructorId, $roleId, $assignedId, null);
                if (!is_string($result)) {
                    $placed++;
                    $assigned++;
                }
            }

            if ($placed < $needed) {
                $short[] = [
                    'exam_schedule_id' => $exam->id,
                    'label' => $exam->displayLabel(),
                    'required' => $exam->required_invigilators,
                    'on_duty' => $onDuty + $placed,
                    // Everyone sent for this examination is already standing in
                    // another hall at this hour.
                    'reason' => 'all_submitted_invigilators_busy',
                ];
            }
        }

        return ['assigned' => $assigned, 'short' => $short];
    }

    /**
     * The error key for a conflict the database refused, or null when the
     * QueryException was something else entirely (and must be rethrown).
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
     * Write one duty row in its own transaction, mirroring the sitting's window.
     *
     * Its own transaction because `autoAssign` calls this in a loop and a
     * rejected candidate must not poison the rest of the run — in PostgreSQL a
     * failed statement aborts the whole transaction it ran in.
     *
     * @param \App\Models\Schedule\ExamSchedule $exam
     * @param int $instructorId
     * @param int $roleId INVIGILATOR_ROLE value id
     * @param int $statusId INVIGILATION_STATUS value id
     * @param string|null $remark
     *
     * @return \App\Models\Invigilation\ExamInvigilatorAssignment|string
     */
    private function writeAssignment(ExamSchedule $exam, int $instructorId, int $roleId, int $statusId, ?string $remark) {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $assignment = ExamInvigilatorAssignment::create([
                'exam_schedule_id' => $exam->id,
                'instructor_id' => $instructorId,
                // Mirrored, and guarded by the composite FK — they cannot
                // disagree with the sitting.
                'exam_date' => $exam->exam_date,
                'start_time' => $exam->start_time,
                'end_time' => $exam->end_time,
                'role_lookup_value_id' => $roleId,
                'status_lookup_value_id' => $statusId,
                'state' => STATE_ACTIVE,
                'assigned_by_id' => Auth::id(),
                'assigned_at' => now(),
                'remark' => $remark,
            ]);

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

        return $assignment;
    }

    /**
     * The instructors departments have submitted for this exam's scope.
     *
     * An exam belongs to a semester and an exam type; a request covers exactly
     * that pair. Only a `sent` or `closed` request counts — a draft has not
     * been asked yet, while a closed one keeps the people it collected.
     *
     * @param \App\Models\Schedule\ExamSchedule $exam
     * @return \Illuminate\Support\Collection
     */
    private function submittedFor(ExamSchedule $exam): Collection {
        return InvigilationSubmission::query()
            ->whereHas('requestDepartment.request', fn ($query) => $query
                ->where('semester_id', $exam->semester_id)
                ->where('exam_type_lookup_value_id', $exam->exam_type_lookup_value_id)
                ->whereHas('status', fn ($status) => $status->whereIn('code', [
                    INVIGILATION_REQUEST_STATUS_SENT,
                    INVIGILATION_REQUEST_STATUS_CLOSED,
                ])))
            ->whereHas('instructor', fn ($query) => $query->where('can_invigilate', true)->where('is_active', true))
            ->pluck('instructor_id')
            ->unique()
            ->values();
    }

    /**
     * Who may be put on this sitting, fewest current duties first.
     *
     * The pool is exactly the people departments SENT for this examination —
     * nothing else. There used to be an availability model on top of this, with
     * declared windows per instructor and a fallback when no request had been
     * raised; it has been removed. Sending someone IS the declaration that they
     * are available, so asking departments to state it twice was bookkeeping
     * that earned nothing and could only disagree with itself.
     *
     * An empty result means nobody has been sent for this semester and exam
     * type yet. That is reported as `no_invigilators_submitted` rather than
     * quietly staffing the exam from whoever happens to exist.
     *
     * @param \App\Models\Schedule\ExamSchedule $exam
     * @return \Illuminate\Support\Collection
     */
    private function candidatesFor(ExamSchedule $exam): Collection {
        $submitted = $this->submittedFor($exam);

        if ($submitted->isEmpty()) {
            return $submitted;
        }

        $dutyCounts = ExamInvigilatorAssignment::query()
            ->whereIn('instructor_id', $submitted)
            ->where('state', STATE_ACTIVE)
            ->selectRaw('instructor_id, count(*) as duty_count')
            ->groupBy('instructor_id')
            ->pluck('duty_count', 'instructor_id');

        // Fewest duties first so the load spreads; the id tiebreak keeps a run
        // reproducible. Double-booking is refused by the EXCLUDE constraint, so
        // this only has to choose well, not choose safely.
        return $submitted
            ->sortBy(fn (int $instructorId): array => [(int) ($dutyCounts[$instructorId] ?? 0), $instructorId])
            ->values();
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param \App\Models\Schedule\ExamSchedule $exam
     * @param int $instructorId
     *
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(ExamSchedule $exam, int $instructorId): ?string {
        $instructor = Instructor::find($instructorId);
        if (!$instructor?->can_invigilate || !$instructor->is_active) {
            return 'instructor_cannot_invigilate';
        }

        // No availability check: a department that sent someone has already
        // said they can invigilate this examination, and the double-booking
        // EXCLUDE constraint still stops the same person being in two halls at
        // once. Manual assignment is deliberately NOT restricted to the
        // submitted pool — a registrar filling a hall an hour before an exam
        // needs to be able to put a name in without a request round-trip.

        return null;
    }

    /**
     * Whether a duty may move to the requested status.
     *
     * @param \App\Models\Invigilation\ExamInvigilatorAssignment $assignment
     * @param string $targetCode an INVIGILATION_STATUS code
     *
     * @return string|null an error translation key, or null when the move is legal
     */
    private function guardMove(ExamInvigilatorAssignment $assignment, string $targetCode): ?string {
        $currentCode = $assignment->status?->code;
        if (!$currentCode) {
            return 'status_lookup_value_not_found';
        }

        if ($currentCode === $targetCode) {
            return 'nothing_is_changed';
        }

        if (!in_array($targetCode, self::ALLOWED_MOVES[$currentCode] ?? [], true)) {
            return 'invalid_status_transition';
        }

        return null;
    }

    /**
     * Take one invigilator off a sitting.
     *
     * The derived count is a starting point, not a verdict: a registrar who
     * knows a hall needs one fewer person removes one here, and `required_
     * invigilators` moves with it so the sitting does not immediately show as
     * short of the number it no longer needs.
     *
     * Only a duty nobody has answered yet may be removed outright. Once it has
     * been accepted or declined there is a record of what a person said, and
     * `replace` is the honest way to change it — deleting would erase their
     * answer.
     *
     * @param \App\Models\Invigilation\ExamInvigilatorAssignment $assignment
     *
     * @return \App\Models\Schedule\ExamSchedule|string the sitting, or a translation key
     */
    public function remove(ExamInvigilatorAssignment $assignment) {
        if ($assignment->status?->code !== INVIGILATION_STATUS_ASSIGNED) {
            return 'only_unanswered_duties_can_be_removed';
        }

        $exam = $assignment->examSchedule;
        if (!$exam) {
            return 'exam_schedule_not_found';
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $assignment->delete();

            // Keep the requirement in step with reality. Never below one: a
            // sitting with nobody watching it is not a sitting.
            $exam->required_invigilators = max(1, (int) $exam->required_invigilators - 1);
            $exam->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $exam->fresh();
    }

    /**
     * Raise the number of invigilators a sitting needs by one.
     *
     * The counterpart to `remove`: assigning somebody to a hall that is already
     * fully staffed should not leave it reading as over-staffed, so the
     * requirement follows the decision.
     *
     * @param \App\Models\Schedule\ExamSchedule $exam
     *
     * @return \App\Models\Schedule\ExamSchedule
     */
    public function raiseRequirement(ExamSchedule $exam): ExamSchedule {
        $onDuty = ExamInvigilatorAssignment::query()
            ->where('exam_schedule_id', $exam->id)
            ->where('state', STATE_ACTIVE)
            ->count();

        if ($onDuty > (int) $exam->required_invigilators) {
            $exam->required_invigilators = $onDuty;
            $exam->save();
        }

        return $exam->fresh();
    }
}
