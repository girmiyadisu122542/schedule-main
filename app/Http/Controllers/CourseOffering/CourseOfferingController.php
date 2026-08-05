<?php

namespace App\Http\Controllers\CourseOffering;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseOffering\RecordApprovalRequest;
use App\Http\Requests\CourseOffering\StoreCourseOfferingRequest;
use App\Http\Requests\CourseOffering\UpdateCourseOfferingRequest;
use App\Models\Offering\CourseOffering;
use App\Rules\LookupValueOfType;
use App\Services\Offering\CourseOfferingService;
use App\Services\Offering\OfferingApprovalService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

/**
 * Course offerings are a workflow table — no `is_active`, no `state`, so no
 * changeState action. The status moves through `submit` and the approval trail
 * (step 10), both guarded by `lookup_transitions`.
 */
class CourseOfferingController extends Controller {

    /** Relations every read needs to render an offering row. */
    private const EAGER = ['semester', 'course', 'department', 'program', 'section.program', 'instructor', 'status', 'createdBy', 'submittedBy'];

    /**
     * The detail read also carries the approval trail, in order.
     *
     * Spread, not `+`: array union keeps the left operand's numeric keys and
     * silently drops every colliding one, so `self::EAGER + [...]` would load
     * none of these.
     */
    private const EAGER_DETAIL = [...self::EAGER, 'approvals.level', 'approvals.decision', 'approvals.actor'];

    /**
     * List offerings with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeCourseOffering() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');

        $offerings = CourseOffering::query()
            ->with(self::EAGER)
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->whereHas('course', fn ($query) => $query->where('code', 'ilike', "%{$search}%"))
                            ->orWhereHas('course', fn ($query) => $query->jsonbLangValueSearch('title', $search, true));
                    });
            })
            ->when($request->input('semester_id'), fn ($query) => $query->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->input('course_id'), fn ($query) => $query->where('course_id', (int) $request->input('course_id')))
            ->when($request->input('department_id'), fn ($query) => $query->where('department_id', (int) $request->input('department_id')))
            ->when($request->input('section_id'), fn ($query) => $query->where('section_id', (int) $request->input('section_id')))
            ->when($request->input('instructor_id'), fn ($query) => $query->where('instructor_id', (int) $request->input('instructor_id')))
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $request->input('status_code'))))
            ->latest('updated_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $offerings->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => CourseOffering::extractPagination($offerings),
        ]);
    }

    /**
     * Show an offering by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeCourseOffering()) {
            return Response::_403();
        }

        $offering = CourseOffering::query()
            ->with(self::EAGER_DETAIL)
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        return Response::_200([
            'data' => $offering->resource(),
            // The append-only decision trail, oldest first.
            'approvals' => $offering->approvals->sortBy('sequence')->values()->collection(),
        ]);
    }

    /**
     * Create an offering. It always starts at `draft`.
     *
     * @param \App\Http\Requests\CourseOffering\StoreCourseOfferingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCourseOfferingRequest $request): JsonResponse {
        try {
            $result = app(CourseOfferingService::class)->createOffering($request->validated());
        } catch (\Illuminate\Database\QueryException $exception) {
            // The composite / partial uniques stop the same course being offered
            // twice to one cohort in one semester.
            return Response::_422(Message::get('offering_already_exists'));
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_course_offering'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('course_offering_created_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Update an offering. Only a draft or rejected offering may be edited.
     *
     * @param \App\Http\Requests\CourseOffering\UpdateCourseOfferingRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCourseOfferingRequest $request, $id): JsonResponse {
        $offering = CourseOffering::with('status')->find($id);
        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        try {
            $result = app(CourseOfferingService::class)->updateOffering($offering, $request->validated());
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('offering_already_exists'));
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_course_offering'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('course_offering_updated_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Delete an offering. Only a draft may be discarded — once it has entered
     * the approval chain its trail is genuine history.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteCourseOffering()) {
            return Response::_403();
        }

        $offering = CourseOffering::with(['status', 'course', 'section.program'])->find($id);
        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        if ($offering->status?->code !== COURSE_OFFERING_STATUS_DRAFT) {
            return Response::_422(Message::get('only_draft_offerings_can_be_deleted'));
        }

        $bindings = ['name' => $offering->displayLabel()];

        try {
            $offering->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('course_offering_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('course_offering_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Submit an offering into the approval chain (no body).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit($id): JsonResponse {
        if (!$this->userCanSubmitCourseOffering()) {
            return Response::_403();
        }

        $offering = CourseOffering::with(['status', 'course', 'section.program'])->find($id);
        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        try {
            $result = app(CourseOfferingService::class)->submitOffering($offering);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_course_offering'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('course_offering_submitted_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Record one tier's decision on an offering: append a trail row and move
     * the offering's status, in one transaction.
     *
     * @param \App\Http\Requests\CourseOffering\RecordApprovalRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordApproval(RecordApprovalRequest $request, $id): JsonResponse {
        $offering = CourseOffering::with(['status', 'course', 'section.program'])->find($id);
        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        try {
            $result = app(OfferingApprovalService::class)->record($offering, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_record_approval'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $offering->refresh()->load('status');

        return Response::_201([
            'data' => $result->fresh(['level', 'decision', 'actor'])->resource(),
            'offering' => $offering->fresh(self::EAGER)->resource(),
            'message' => Message::get('approval_recorded_successfully', [
                'name' => $offering->displayLabel(),
                'status' => $offering->status?->name__localized,
            ]),
        ]);
    }

    /**
     * Move an offering along the COURSE_OFFERING_STATUS lifecycle.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus($id): JsonResponse {
        if (!$this->userCanApproveCourseOffering()) {
            return Response::_403();
        }

        $offering = CourseOffering::with(['status', 'course', 'section.program'])->find($id);
        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'status_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(COURSE_OFFERING_STATUS, 'invalid_offering_status'),
            ],
        ], Message::get('course_offering') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        try {
            $result = app(CourseOfferingService::class)->changeStatus($offering, (int) request()->status_lookup_value_id);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_course_offering'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('course_offering_status_changed_successfully', [
                'name' => $result->displayLabel(),
                'status' => $result->status?->name__localized,
            ]),
        ]);
    }
}
