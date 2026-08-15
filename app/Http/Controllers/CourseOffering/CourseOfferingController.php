<?php

namespace App\Http\Controllers\CourseOffering;

use App\Http\Controllers\Concerns\HandlesMasterDataImportExport;
use App\Http\Controllers\Concerns\ScopesOfferingsToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseOffering\RecordApprovalRequest;
use App\Http\Requests\CourseOffering\StoreCourseOfferingRequest;
use App\Http\Requests\CourseOffering\UpdateCourseOfferingRequest;
use App\Http\Requests\Import\CourseOfferingImportRequest;
use App\Models\Offering\CourseOffering;
use App\Services\Offering\CourseOfferingService;
use App\Services\Offering\OfferingApprovalService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\OfferingColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Course offerings are a workflow table — no `is_active`, no `state`, so no
 * changeState action. The status moves through `submit` and the approval trail
 * (step 10), both guarded by `lookup_transitions`.
 */
class CourseOfferingController extends Controller {
    use HandlesMasterDataImportExport;
    use ScopesOfferingsToDepartment;

    /**
     * The review queues the list screen offers as tabs.
     *
     * Queues are not the same axis as `status_code`: "awaiting me" spans four
     * statuses and depends on who is asking, and "my drafts" spans two. They
     * stack with the filters rather than replacing them.
     */
    public const QUEUE_AWAITING_ME = 'awaiting_me';
    public const QUEUE_MY_DRAFTS = 'my_drafts';
    public const QUEUE_IN_PROGRESS = 'in_progress';
    public const QUEUE_RETURNED = 'returned';
    public const QUEUE_APPROVED = 'approved';
    public const QUEUE_REJECTED = 'rejected';

    /** @var array<int, string> */
    public const QUEUES = [
        self::QUEUE_AWAITING_ME,
        self::QUEUE_MY_DRAFTS,
        self::QUEUE_IN_PROGRESS,
        self::QUEUE_RETURNED,
        self::QUEUE_APPROVED,
        self::QUEUE_REJECTED,
    ];

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

        $offerings = $this->filteredQuery($request)->paginate(static::getPerPage());

        return Response::_200([
            'data' => $offerings->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => CourseOffering::extractPagination($offerings),
        ]);
    }

    /**
     * The filtered, SCOPED builder behind `index`, `export` and `summary`.
     *
     * The scope is applied here rather than at each call site so no read path
     * can forget it — offerings were the one workflow with none, which meant
     * `GET /offerings` handed the whole institution to every teacher.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function filteredQuery(Request $request) {
        $search = $request->input('search');
        $queue = $request->input('queue');

        $query = CourseOffering::query()->with(self::EAGER);

        $this->applyOfferingScope($query);

        return $query
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->whereHas('course', fn ($query) => $query->where('code', 'like', "%{$search}%"))
                            // AND, not OR, INSIDE the whereHas callback. The
                            // third argument to `jsonbLangValueSearch` emits
                            // `orWhereRaw`, which here ORs against the
                            // correlation `course_offerings.course_id =
                            // courses.id` — always true for the row's own
                            // course, so the EXISTS matched every offering and
                            // the search silently returned everything.
                            ->orWhereHas('course', fn ($query) => $query->jsonbLangValueSearch('title', $search));
                    });
            })
            ->when($request->input('semester_id'), fn ($query) => $query->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->input('course_id'), fn ($query) => $query->where('course_id', (int) $request->input('course_id')))
            ->when($request->input('department_id'), fn ($query) => $query->where('department_id', (int) $request->input('department_id')))
            ->when($request->input('program_id'), fn ($query) => $query->where('program_id', (int) $request->input('program_id')))
            ->when($request->input('section_id'), fn ($query) => $query->where('section_id', (int) $request->input('section_id')))
            ->when($request->input('instructor_id'), fn ($query) => $query->where('instructor_id', (int) $request->input('instructor_id')))
            // The cascade's top level, reached through the offering's department.
            ->when($request->input('college_id'), function ($query) use ($request) {
                $query->whereHas('department', fn ($query) => $query->where('college_id', (int) $request->input('college_id')));
            })
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $request->input('status_code'))))
            ->when($queue, fn ($query) => $this->applyQueue($query, (string) $queue))
            ->latest('updated_at');
    }

    /**
     * Narrow the list to one of the review queues the screen offers.
     *
     * `awaiting_me` is the one that has to be answered server-side: it means
     * "the tier due on this offering is one I hold the key for, on a department
     * my scope permits", and a client cannot evaluate either half.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $queue
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyQueue($query, string $queue) {
        $byStatus = fn (array $codes) => $query->whereHas('status', fn ($status) => $status->whereIn('code', $codes));

        return match ($queue) {
            self::QUEUE_AWAITING_ME => $this->applyAwaitingMe($query),
            self::QUEUE_MY_DRAFTS => $query
                ->where('created_by_id', auth()->id())
                ->whereHas('status', fn ($status) => $status->whereIn('code', CourseOfferingService::EDITABLE_STATUS_CODES)),
            self::QUEUE_IN_PROGRESS => $byStatus([
                COURSE_OFFERING_STATUS_SUBMITTED,
                COURSE_OFFERING_STATUS_COMMITTEE_APPROVED,
                COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED,
                COURSE_OFFERING_STATUS_COLLEGE_APPROVED,
            ]),
            self::QUEUE_RETURNED => $byStatus([COURSE_OFFERING_STATUS_RETURNED]),
            self::QUEUE_APPROVED => $byStatus([COURSE_OFFERING_STATUS_REGISTRAR_APPROVED]),
            self::QUEUE_REJECTED => $byStatus([COURSE_OFFERING_STATUS_REJECTED]),
            default => $query,
        };
    }

    /**
     * The offerings waiting on THIS user's signature.
     *
     * Built from the statuses whose due tier the caller both holds a key for and
     * has the standing to act on. A user who qualifies for no tier gets a query
     * that matches nothing, which is the honest answer.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyAwaitingMe($query) {
        $statusCodes = [];

        foreach (OfferingApprovalService::statusesByDueLevel() as $statusCode => $levelCode) {
            $permissionKey = PERMISSION_BY_APPROVAL_LEVEL[$levelCode] ?? null;

            if ($permissionKey && $this->userCan($permissionKey) && $this->scopeQualifiesForTier($levelCode)) {
                $statusCodes[] = $statusCode;
            }
        }

        if (!$statusCodes) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('status', fn ($status) => $status->whereIn('code', $statusCodes));
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

        if (!$this->scopeAllowsOffering($offering)) {
            return Response::_403();
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

        if (!$this->scopeAllowsAuthoringOffering($offering)) {
            return Response::_403();
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

        if (!$this->scopeAllowsAuthoringOffering($offering)) {
            return Response::_403();
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

        // WHICH tier is due is a property of the offering, never of the request.
        $dueLevel = OfferingApprovalService::dueLevelForStatus($offering->status?->code);
        if (!$dueLevel) {
            return Response::_422(Message::get('offering_is_not_awaiting_a_decision'));
        }

        $permissionKey = PERMISSION_BY_APPROVAL_LEVEL[$dueLevel] ?? null;
        if (!$permissionKey || !$this->userCan($permissionKey) || !$this->scopeAllowsTier($dueLevel, $offering)) {
            return Response::_403();
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
     * Put a rejected offering back in its author's hands.
     *
     * Replaces the old `change-status` endpoint, which accepted any target and
     * wrote no trail row — making it a way to walk an offering to
     * `registrar_approved` with no approvals recorded at all. This one takes no
     * target, so `rejected → draft` is the only move it can perform.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reopen($id): JsonResponse {
        if (!$this->userCanReopenCourseOffering()) {
            return Response::_403();
        }

        $offering = CourseOffering::with(['status', 'course', 'section.program'])->find($id);
        if (!$offering) {
            return Response::_404(Message::get('course_offering_not_found'));
        }

        if (!$this->scopeAllowsTier(APPROVAL_LEVEL_DEPARTMENT, $offering)) {
            return Response::_403();
        }

        try {
            $result = app(CourseOfferingService::class)->reopen($offering);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_course_offering'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('course_offering_reopened_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Import offerings from a spreadsheet.
     *
     * Declared here rather than inherited so the route type-hints the CONCRETE
     * request — `ImportRequest` is abstract and the container cannot build it.
     *
     * @param \App\Http\Requests\Import\CourseOfferingImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(CourseOfferingImportRequest $request): JsonResponse {
        return $this->handleImport($request);
    }

    /**
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    protected function columnMap(): AbstractColumnMap {
        return new OfferingColumnMap();
    }

    /**
     * @return bool
     */
    protected function canExportEntity(): bool {
        return $this->userCanExportCourseOffering();
    }

    /**
     * @return bool
     */
    protected function canImportEntity(): bool {
        return $this->userCanImportCourseOffering();
    }

    /**
     * Per-queue counts for the list screen's tabs.
     *
     * Runs each queue through the same scoped builder the list uses, so a badge
     * can never promise rows the list will not show.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request): JsonResponse {
        if (!$this->userCanSeeCourseOffering()) {
            return Response::_403();
        }

        $counts = [];

        foreach (self::QUEUES as $queue) {
            $counts[$queue] = $this->filteredQuery($request->merge(['queue' => $queue]))->count();
        }

        return Response::_200(['data' => $counts]);
    }
}
