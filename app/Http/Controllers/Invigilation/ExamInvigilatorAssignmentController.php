<?php

namespace App\Http\Controllers\Invigilation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invigilation\AssignInvigilatorRequest;
use App\Http\Requests\Invigilation\AutoAssignInvigilatorsRequest;
use App\Http\Requests\Invigilation\ReplaceInvigilatorRequest;
use App\Http\Requests\Invigilation\RespondToAssignmentRequest;
use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Services\Invigilation\ExamInvigilatorAssignmentService;
use App\Services\Lookup\LookupService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Invigilation duties. As on the schedule tables there is no `changeState`
 * action: `state` is the conflict-liveness flag, and it moves only with the
 * status — declining or being replaced frees the invigilator.
 */
class ExamInvigilatorAssignmentController extends Controller {

    /** Relations every read needs to render a duty row. */
    private const EAGER = [
        'examSchedule.courseOffering.course',
        'examSchedule.examType',
        // The roster prints the hall, so the embed needs it loaded.
        'examSchedule.room',
        'instructor.department',
        'instructor',
        'role',
        'status',
        'assignedBy',
    ];

    /**
     * List duties with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeInvigilatorAssignment() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');

        $assignments = ExamInvigilatorAssignment::query()
            ->with(self::EAGER)
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->whereHas('instructor', fn ($query) => $query->where('employee_no', 'ilike', "%{$search}%"))
                            ->orWhereHas('instructor', fn ($query) => $query->jsonbLangValueSearch('full_name', $search, true))
                            ->orWhereHas('examSchedule.courseOffering.course', fn ($query) => $query->where('code', 'ilike', "%{$search}%"));
                    });
            })
            ->when($request->input('exam_schedule_id'), fn ($query) => $query->where('exam_schedule_id', (int) $request->input('exam_schedule_id')))
            ->when($request->input('instructor_id'), fn ($query) => $query->where('instructor_id', (int) $request->input('instructor_id')))
            ->when($request->input('exam_date'), fn ($query) => $query->whereDate('exam_date', $request->input('exam_date')))
            ->when($request->input('semester_id'), fn ($query) => $query->whereHas('examSchedule', fn ($query) => $query->where('semester_id', (int) $request->input('semester_id'))))
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $request->input('status_code'))))
            ->when($request->input('role_code'), fn ($query) => $query->whereHas('role', fn ($query) => $query->where('code', $request->input('role_code'))))
            // A duty roster reads by sitting, not by edit time.
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $assignments->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => ExamInvigilatorAssignment::extractPagination($assignments),
        ]);
    }

    /**
     * Put one instructor on duty by hand.
     *
     * @param \App\Http\Requests\Invigilation\AssignInvigilatorRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AssignInvigilatorRequest $request): JsonResponse {
        try {
            $result = app(ExamInvigilatorAssignmentService::class)->assign($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_assign_invigilator'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('invigilator_assigned_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Staff every sitting in a semester from the offered availability windows.
     *
     * @param \App\Http\Requests\Invigilation\AutoAssignInvigilatorsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function autoAssign(AutoAssignInvigilatorsRequest $request): JsonResponse {
        try {
            $result = app(ExamInvigilatorAssignmentService::class)->autoAssign((int) $request->validated('semester_id'));
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_assign_invigilator'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result,
            'message' => Message::get('invigilators_assigned_successfully', [
                'assigned' => $result['assigned'],
                'short' => count($result['short']),
            ]),
        ]);
    }

    /**
     * Record the instructor's answer to a duty.
     *
     * @param \App\Http\Requests\Invigilation\RespondToAssignmentRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respond(RespondToAssignmentRequest $request, $id): JsonResponse {
        $assignment = ExamInvigilatorAssignment::with(['status', 'instructor', 'examSchedule.examType', 'examSchedule.courseOffering.course'])->find($id);
        if (!$assignment) {
            return Response::_404(Message::get('invigilator_assignment_not_found'));
        }

        $decisionCode = LookupService::getValueById((int) $request->validated('status_lookup_value_id'))?->code;
        if (!$decisionCode) {
            return Response::_422(Message::get('invalid_invigilation_decision'));
        }

        try {
            $result = app(ExamInvigilatorAssignmentService::class)->respond($assignment, $decisionCode);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_invigilator_assignment'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('invigilation_response_recorded', [
                'name' => $result->displayLabel(),
                'status' => $result->status?->name__localized,
            ]),
        ]);
    }

    /**
     * Swap one invigilator for another on the same duty.
     *
     * @param \App\Http\Requests\Invigilation\ReplaceInvigilatorRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function replace(ReplaceInvigilatorRequest $request, $id): JsonResponse {
        $assignment = ExamInvigilatorAssignment::with(['status', 'instructor'])->find($id);
        if (!$assignment) {
            return Response::_404(Message::get('invigilator_assignment_not_found'));
        }

        try {
            $result = app(ExamInvigilatorAssignmentService::class)->replace(
                $assignment,
                (int) $request->validated('instructor_id'),
                $request->validated('remark'),
            );
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_invigilator_assignment'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('invigilator_replaced_successfully', ['name' => $result->displayLabel()]),
        ]);
    }
}
