<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ConfirmExamScheduleRequest;
use App\Http\Requests\Schedule\ExamScheduleRequest;
use App\Models\Schedule\ExamSchedule;
use App\Services\Schedule\ExamScheduleService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * The exam timetable. As on `class_schedules` there is no `changeState` action:
 * `state` is the conflict-liveness flag and moves only with the status, through
 * `cancel`.
 */
class ExamScheduleController extends Controller {

    /** Relations every read needs to render an exam row. */
    private const EAGER = [
        'courseOffering.course',
        'courseOffering.section.program',
        'semester',
        'section.program',
        'room',
        'status',
        'examType',
        'createdBy',
        'confirmedBy',
        'publishedBy',
    ];

    /**
     * List exam sittings with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeExamSchedule() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');

        $schedules = ExamSchedule::query()
            ->with(self::EAGER)
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
            ->when($request->input('section_id'), fn ($query) => $query->where('section_id', (int) $request->input('section_id')))
            ->when($request->input('room_id'), fn ($query) => $query->where('room_id', (int) $request->input('room_id')))
            ->when($request->input('exam_date'), fn ($query) => $query->whereDate('exam_date', $request->input('exam_date')))
            ->when($request->input('generation_run_id'), fn ($query) => $query->where('generation_run_id', (int) $request->input('generation_run_id')))
            ->when($request->input('exam_type_code'), fn ($query) => $query->whereHas('examType', fn ($query) => $query->where('code', $request->input('exam_type_code'))))
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $request->input('status_code'))))
            // An exam calendar reads by sitting, not by edit time.
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $schedules->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => ExamSchedule::extractPagination($schedules),
        ]);
    }

    /**
     * Show a sitting by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeExamSchedule()) {
            return Response::_403();
        }

        $schedule = ExamSchedule::query()
            ->with(self::EAGER)
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$schedule) {
            return Response::_404(Message::get('exam_schedule_not_found'));
        }

        return Response::_200([
            'data' => $schedule->resource(),
        ]);
    }

    /**
     * Place one sitting by hand. It always starts at `draft`.
     *
     * @param \App\Http\Requests\Schedule\ExamScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ExamScheduleRequest $request): JsonResponse {
        try {
            $result = app(ExamScheduleService::class)->createSchedule($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_exam_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('exam_schedule_created_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Adjust a draft sitting.
     *
     * @param \App\Http\Requests\Schedule\ExamScheduleRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ExamScheduleRequest $request, $id): JsonResponse {
        $schedule = ExamSchedule::with('status')->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('exam_schedule_not_found'));
        }

        try {
            $result = app(ExamScheduleService::class)->updateSchedule($schedule, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_exam_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('exam_schedule_updated_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Delete a sitting. Only a draft may be discarded — anything further along
     * is cancelled instead, so the row stays as a record of what changed.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteExamSchedule()) {
            return Response::_403();
        }

        $schedule = ExamSchedule::with(['status', 'examType', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('exam_schedule_not_found'));
        }

        if (!$schedule->isDraft()) {
            return Response::_422(Message::get('only_draft_exams_can_be_deleted'));
        }

        $bindings = ['name' => $schedule->displayLabel()];

        try {
            $schedule->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('exam_schedule_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('exam_schedule_deleted_successfully', $bindings),
        ]);
    }

    /**
     * The department confirmation step: ask for it from `draft`, give it from
     * `pending_confirmation`. Which one happens depends on where the sitting
     * already is, not on anything the caller sends.
     *
     * @param \App\Http\Requests\Schedule\ConfirmExamScheduleRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(ConfirmExamScheduleRequest $request, $id): JsonResponse {
        $schedule = ExamSchedule::with(['status', 'examType', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('exam_schedule_not_found'));
        }

        try {
            $result = app(ExamScheduleService::class)->confirm($schedule, $request->validated('confirmation_remark'));
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_exam_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $message = $result->status?->code === EXAM_SCHEDULE_STATUS_CONFIRMED
            ? 'exam_schedule_confirmed_successfully'
            : 'exam_schedule_sent_for_confirmation';

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get($message, ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Publish a sitting (no body).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function publish($id): JsonResponse {
        if (!$this->userCanPublishExamSchedule()) {
            return Response::_403();
        }

        $schedule = ExamSchedule::with(['status', 'examType', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('exam_schedule_not_found'));
        }

        try {
            $result = app(ExamScheduleService::class)->publish($schedule);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_exam_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('exam_schedule_published_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Cancel a sitting: `status -> cancelled` AND `state -> STATE_INACTIVE`,
     * which frees the hall and the cohort's window (no body).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id): JsonResponse {
        if (!$this->userCanCancelExamSchedule()) {
            return Response::_403();
        }

        $schedule = ExamSchedule::with(['status', 'examType', 'courseOffering.course', 'courseOffering.section.program'])->find($id);
        if (!$schedule) {
            return Response::_404(Message::get('exam_schedule_not_found'));
        }

        try {
            $result = app(ExamScheduleService::class)->cancel($schedule);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_exam_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('exam_schedule_cancelled_successfully', ['name' => $result->displayLabel()]),
        ]);
    }
}
