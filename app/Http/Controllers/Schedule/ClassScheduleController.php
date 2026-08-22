<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Concerns\ScopesSchedulesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ClassScheduleRequest;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ClassScheduleException;
use App\Models\Offering\CourseOffering;
use App\Services\Schedule\PlacementSuggestionService;
use App\Models\Physical\Room;
use App\Services\Schedule\ScheduleBulkService;
use App\Services\Schedule\ClassScheduleService;
use App\Http\Requests\Schedule\ScheduleBulkActionRequest;
use App\Services\Common\BulkActionRunner;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Translation\Message;

/**
 * The class timetable. There is no `changeState` action here: `state` is the
 * conflict-liveness flag, and it only ever moves together with the status
 * through `publish` and `cancel` (Final Schema.md §14 design notes).
 */
class ClassScheduleController extends Controller {

    use ScopesSchedulesToDepartment;

    /** Relations every read needs to render a timetable row. */
    private const EAGER = [
        'courseOffering.course',
        'courseOffering.section.program',
        // Grouping for the master timetable — without these the resource
        // fields lazy-load one query per row.
        'courseOffering.department',
        'courseOffering.program',
        'semester',
        'section.program',
        'instructor',
        'room',
        'status',
        'sessionType',
        'createdBy',
        'publishedBy',
    ];

    /**
     * List class meetings with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeClassSchedule() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');

        $schedules = ClassSchedule::query()
            ->with(self::EAGER)
            // Other departments are not this caller's to read.
            ->tap(fn ($query) => $this->applyDepartmentScope($query))
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->whereHas('courseOffering.course', fn ($query) => $query->where('code', 'like', "%{$search}%"))
                            ->orWhereHas('courseOffering.course', fn ($query) => $query->jsonbLangValueSearch('title', $search, true))
                            ->orWhereHas('room', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                    });
            })
            ->when($request->input('semester_id'), fn ($query) => $query->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->input('course_offering_id'), fn ($query) => $query->where('course_offering_id', (int) $request->input('course_offering_id')))
            // The academic hierarchy — a meeting reaches it through its
            // offering, which is where ownership lives (Final Schema.md §12).
            ->when($request->input('college_id'), fn ($query) => $query->whereHas(
                'courseOffering.department',
                fn ($offering) => $offering->where('college_id', (int) $request->input('college_id')),
            ))
            ->when($request->input('department_id'), fn ($query) => $query->whereHas(
                'courseOffering',
                fn ($offering) => $offering->where('department_id', (int) $request->input('department_id')),
            ))
            // The offering's own program is nullable, and the cohort carries
            // the authoritative one — match either, or a section-scoped
            // offering would drop out of its own programme's timetable.
            ->when($request->input('program_id'), function ($query) use ($request) {
                $programId = (int) $request->input('program_id');

                $query->whereHas('courseOffering', fn ($offering) => $offering
                    ->where('program_id', $programId)
                    ->orWhereHas('section', fn ($section) => $section->where('program_id', $programId)));
            })
            ->when($request->input('section_id'), fn ($query) => $query->where('section_id', (int) $request->input('section_id')))
            ->when($request->input('instructor_id'), fn ($query) => $query->where('instructor_id', (int) $request->input('instructor_id')))
            ->when($request->input('room_id'), fn ($query) => $query->where('room_id', (int) $request->input('room_id')))
            ->when($request->input('day_of_week'), fn ($query) => $query->where('day_of_week', (int) $request->input('day_of_week')))
            ->when($request->input('generation_run_id'), fn ($query) => $query->where('generation_run_id', (int) $request->input('generation_run_id')))
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $request->input('status_code'))))
            // A timetable reads by slot, not by edit time.
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $schedules->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => ClassSchedule::extractPagination($schedules),
        ]);
    }

    /**
     * Show a meeting by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::query()
            ->with(self::EAGER)
            ->tap(fn ($query) => $this->applyDepartmentScope($query))
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        return Response::_200([
            'data' => $schedule->resource(),
        ]);
    }

    /**
     * Place one meeting by hand. It always starts at `draft`.
     *
     * @param \App\Http\Requests\Schedule\ClassScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ClassScheduleRequest $request): JsonResponse {
        if (!$this->scopeAllowsOffering((int) $request->input('course_offering_id'))) {
            return Response::_403();
        }

        $service = app(ClassScheduleService::class);
        $validated = $request->validated();

        // A batch keeps the batch response shape even when it carries one slot,
        // so a caller that sends `slots` always parses the same thing back.
        if (!empty($validated['slots'])) {
            try {
                $outcome = $service->createSchedules($validated);
            } catch (\Exception $exception) {
                return Response::_500(Message::get('unable_to_create_class_schedule'));
            }

            $payload = [
                'data' => [
                    'created' => collect($outcome['created'])
                        ->map(fn ($schedule) => $schedule->fresh(self::EAGER)->resource())
                        ->all(),
                    'failed' => $outcome['failed'],
                    'created_count' => count($outcome['created']),
                    'failed_count' => count($outcome['failed']),
                ],
                'message' => Message::get('class_schedules_created_summary', [
                    'created' => count($outcome['created']),
                    'failed' => count($outcome['failed']),
                ]),
            ];

            // The status tells the caller what happened without reading counts:
            // 201 everything landed, 207 some did, 422 none did. A 201 on a
            // batch where nothing was written would be a lie, and a 422 on one
            // that wrote three of four would throw away work that succeeded.
            if (empty($outcome['created'])) {
                return Response::_422(
                    $payload['message'],
                    $outcome['failed'],
                );
            }

            return empty($outcome['failed'])
                ? Response::_201($payload)
                : Response::_207($payload);
        }

        try {
            $result = $service->createSchedule($validated);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('class_schedule_created_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Adjust a draft meeting.
     *
     * @param \App\Http\Requests\Schedule\ClassScheduleRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ClassScheduleRequest $request, $id): JsonResponse {
        $schedule = ClassSchedule::with(['status', 'courseOffering'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        try {
            $result = app(ClassScheduleService::class)->updateSchedule($schedule, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('class_schedule_updated_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Delete a meeting. Only a draft may be discarded — a published meeting is
     * cancelled instead, so the row stays as a record of what changed.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::with(['status', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        if (!$schedule->isDraft()) {
            return Response::_422(Message::get('only_draft_schedules_can_be_deleted'));
        }

        $bindings = ['name' => $schedule->displayLabel()];

        try {
            $schedule->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('class_schedule_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('class_schedule_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Publish a meeting: `draft -> published` (no body).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Take one lifecycle decision over many meetings at once.
     *
     * Every row goes through the SAME service call the single-item endpoints
     * use, so publishing forty meetings applies the same permission, scope and
     * lifecycle rules forty times. Nothing here can approve a row that the
     * single endpoint would refuse.
     *
     * The result is therefore partial by design: the response says how many
     * went through and names the ones that did not, with the reason. See
     * {@see \App\Services\Common\BulkActionRunner} for why that beats an
     * all-or-nothing batch.
     *
     * @param \App\Http\Requests\Schedule\ScheduleBulkActionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkAction(ScheduleBulkActionRequest $request): JsonResponse {
        $action = $request->input('action');
        $service = app(ClassScheduleService::class);

        $permitted = match ($action) {
            'publish' => $this->userCanPublishClassSchedule(),
            'confirm' => $this->userCanConfirmClassSchedule(),
            'cancel' => $this->userCanCancelClassSchedule(),
            'delete' => $this->userCanDeleteClassSchedule(),
            default => false,
        };

        if (!$permitted) {
            return Response::_403();
        }

        $remark = $request->input('remark');

        $outcome = BulkActionRunner::run(
            $request->input('schedule_ids', []),
            fn ($id) => ClassSchedule::with(['status', 'courseOffering.course', 'courseOffering.section.program'])
                ->find($id),
            function (ClassSchedule $schedule) use ($action, $service, $remark) {
                // Scope is re-checked per row, exactly as the single-item
                // endpoints do — a list of ids is not a claim of ownership.
                if (!$this->scopeAllowsSchedule($schedule)) {
                    return 'schedule_out_of_scope';
                }

                return match ($action) {
                    'publish' => $service->publish($schedule),
                    'confirm' => $service->confirm($schedule, $remark),
                    'cancel' => $service->cancel($schedule),
                    'delete' => $this->deleteForBulk($schedule),
                    default => 'action_not_found',
                };
            },
            fn (ClassSchedule $schedule) => $schedule->displayLabel(),
        );

        return Response::_200([
            'data' => $outcome,
            'message' => Message::get('bulk_action_completed', [
                'succeeded' => $outcome['succeeded'],
                'failed' => count($outcome['failed']),
            ]),
        ]);
    }

    /**
     * Delete one meeting for a bulk run, in the shape the runner expects.
     *
     * Only a draft may be deleted — a published meeting is what students are
     * reading, and the way to withdraw one is `cancel`, which keeps the record.
     *
     * @param \App\Models\Schedule\ClassSchedule $schedule
     * @return \App\Models\Schedule\ClassSchedule|string
     */
    private function deleteForBulk(ClassSchedule $schedule) {
        if (!$schedule->isDraft()) {
            return 'only_draft_schedules_can_be_deleted';
        }

        $schedule->delete();

        return $schedule;
    }

    public function publish($id): JsonResponse {
        if (!$this->userCanPublishClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::with(['status', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        try {
            $result = app(ClassScheduleService::class)->publish($schedule);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('class_schedule_published_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Cancel a meeting: `status -> cancelled` AND `state -> STATE_INACTIVE`,
     * which frees the slot for someone else (no body).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id): JsonResponse {
        if (!$this->userCanCancelClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::with(['status', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        try {
            $result = app(ClassScheduleService::class)->cancel($schedule);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('class_schedule_cancelled_successfully', ['name' => $result->displayLabel()]),
        ]);
    }
    /**
     * Pin or unpin a session (C15).
     *
     * A pinned row is one somebody placed by hand and does not want the next
     * generation run to take away. It stays live, so the EXCLUDE constraints
     * keep treating its slot as taken and the generator schedules around it
     * rather than over it.
     *
     * Only a draft can be pinned: a published session is already protected,
     * and pinning it would suggest a weaker guarantee than it has.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pin(Request $request, $id): JsonResponse {
        $schedule = ClassSchedule::with(['status', 'courseOffering'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        if (!$schedule->isDraft()) {
            return Response::_422(Message::get('only_draft_schedules_can_be_pinned'));
        }

        $schedule->forceFill(['is_pinned' => (bool) $request->boolean('is_pinned', true)])->save();

        return Response::_200([
            'data' => $schedule->fresh(self::EAGER)->resource(),
            'message' => Message::get($schedule->is_pinned ? 'class_schedule_pinned_successfully' : 'class_schedule_unpinned_successfully'),
        ]);
    }

    /**
     * Cancel one week of a recurring session (C18).
     *
     * Not the same as cancelling the session: the weekly rule stays live, so
     * the room and the instructor remain booked for every other week and the
     * EXCLUDE constraints go on protecting them. Only this date is skipped.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addException(Request $request, $id): JsonResponse {
        $schedule = ClassSchedule::with(['status', 'courseOffering'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        $validated = $request->validate([
            'exception_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // The weekday has to match, or the cancellation names a date on which
        // the class does not meet — which would silently do nothing.
        if ((int) date('N', strtotime($validated['exception_date'])) !== (int) $schedule->day_of_week) {
            return Response::_422(Message::get('exception_date_is_not_a_meeting_day'));
        }

        $exception = ClassScheduleException::firstOrNew([
            'class_schedule_id' => $schedule->id,
            'exception_date' => $validated['exception_date'],
        ]);

        // Cancelling the same week twice is a no-op, not a second record.
        $exception->fill(['reason' => $validated['reason'] ?? null]);
        $exception->created_by_id ??= Auth::id();
        $exception->save();

        return Response::_200([
            'data' => $exception->fresh('createdBy')->resource(),
            'message' => Message::get('class_session_week_cancelled_successfully'),
        ]);
    }

    /**
     * Reinstate a week that was cancelled.
     *
     * @param int $id
     * @param int $exceptionId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeException($id, $exceptionId): JsonResponse {
        $schedule = ClassSchedule::with(['status', 'courseOffering'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        $exception = ClassScheduleException::where('class_schedule_id', $schedule->id)->find($exceptionId);
        if (!$exception) {
            return Response::_404(Message::get('class_schedule_exception_not_found'));
        }

        $exception->delete();

        return Response::_200([
            'message' => Message::get('class_session_week_reinstated_successfully'),
        ]);
    }

    /**
     * Where this offering would fit (C24).
     *
     * Guided resolution: a reason code says what went wrong, this says what to
     * do about it. The search is read-only and takes no locks, so a suggestion
     * can go stale between being shown and being taken — acting on it goes
     * through `store`, where the EXCLUDE constraints have the final word.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suggestions(Request $request): JsonResponse {
        if (!$this->userCanSeeClassSchedule()) {
            return Response::_403();
        }

        $offering = CourseOffering::with(['course', 'program', 'section.program', 'additionalSections'])
            ->find($request->input('course_offering_id'));

        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        if (!$this->scopeAllowsOffering((int) $offering->id)) {
            return Response::_403();
        }

        return Response::_200([
            'data' => app(PlacementSuggestionService::class)->forClassOffering(
                $offering,
                (int) ($request->input('limit') ?: 5),
            ),
        ]);
    }

    /**
     * The department confirmation step (C26).
     *
     * Ask for it from `draft`, give it from `pending_confirmation` — which one
     * happens depends on where the session already is, not on anything the
     * caller sends. Same shape as the exam endpoint, deliberately.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request, $id): JsonResponse {
        if (!$this->userCanConfirmClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::with(['status', 'courseOffering'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        $validated = $request->validate(['confirmation_remark' => ['nullable', 'string', 'max:1000']]);

        try {
            $result = app(ClassScheduleService::class)->confirm($schedule, $validated['confirmation_remark'] ?? null);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $message = $result->status?->code === CLASS_SCHEDULE_STATUS_CONFIRMED
            ? 'class_schedule_confirmed_successfully'
            : 'class_schedule_sent_for_confirmation';

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get($message),
        ]);
    }

    /**
     * The department disagrees — send it back to draft with a reason.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function returnToDraft(Request $request, $id): JsonResponse {
        if (!$this->userCanConfirmClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::with(['status', 'courseOffering'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        if (!$this->scopeAllowsSchedule($schedule)) {
            return Response::_403();
        }

        $validated = $request->validate(['confirmation_remark' => ['nullable', 'string', 'max:1000']]);

        try {
            $result = app(ClassScheduleService::class)->returnToDraft($schedule, $validated['confirmation_remark'] ?? null);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('class_schedule_returned_to_draft'),
        ]);
    }

    /**
     * Bulk moves (C17): shift by weekday, swap room, or cancel a date range.
     *
     * One endpoint with an `action` rather than three, because they share the
     * whole guard chain — permission, scope, and the rule that a published
     * session is not moved silently.
     *
     * Reports per-row outcomes instead of aborting: a bulk move across forty
     * sessions will hit a clash somewhere, and stopping dead would leave half
     * the change applied with no way to see which half.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulk(Request $request): JsonResponse {
        if (!$this->userCanUpdateClassSchedule()) {
            return Response::_403();
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:shift_days,swap_room,cancel_range'],
            'schedule_ids' => ['required_unless:action,cancel_range', 'array', 'min:1'],
            'schedule_ids.*' => ['integer'],
            'shift_days' => ['required_if:action,shift_days', 'integer', 'between:-6,6'],
            'room_id' => ['required_if:action,swap_room', 'integer', 'exists:' . Room::getTableName() . ',id'],
            'semester_id' => ['required_if:action,cancel_range', 'integer'],
            'from_date' => ['required_if:action,cancel_range', 'date'],
            'to_date' => ['required_if:action,cancel_range', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Every row has to be one this caller may act on. Checked before any
        // write, so a partially-permitted batch is refused outright rather
        // than half-applied.
        foreach ($validated['schedule_ids'] ?? [] as $id) {
            $schedule = ClassSchedule::with('courseOffering')->find($id);

            if ($schedule && !$this->scopeAllowsSchedule($schedule)) {
                return Response::_403();
            }
        }

        $service = app(ScheduleBulkService::class);

        try {
            $result = match ($validated['action']) {
                'shift_days' => $service->shiftDays($validated['schedule_ids'], (int) $validated['shift_days']),
                'swap_room' => $service->swapRoom($validated['schedule_ids'], (int) $validated['room_id']),
                'cancel_range' => $service->cancelDateRange(
                    (int) $validated['semester_id'],
                    $validated['from_date'],
                    $validated['to_date'],
                    $validated['reason'] ?? null,
                ),
            };
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        return Response::_200([
            'data' => $result,
            'message' => Message::get('bulk_schedule_change_applied', [
                'moved' => $result['moved'] ?? $result['cancelled'] ?? 0,
                'failed' => count($result['failed'] ?? []),
            ]),
        ]);
    }
}
