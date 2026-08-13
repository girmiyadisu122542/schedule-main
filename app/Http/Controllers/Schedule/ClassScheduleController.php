<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Concerns\ScopesSchedulesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ClassScheduleRequest;
use App\Models\Schedule\ClassSchedule;
use App\Services\Schedule\ClassScheduleService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                            ->whereHas('courseOffering.course', fn ($query) => $query->where('code', 'ilike', "%{$search}%"))
                            ->orWhereHas('courseOffering.course', fn ($query) => $query->jsonbLangValueSearch('title', $search, true))
                            ->orWhereHas('room', fn ($query) => $query->where('code', 'ilike', "%{$search}%"));
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

        try {
            $result = app(ClassScheduleService::class)->createSchedule($request->validated());
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
}
