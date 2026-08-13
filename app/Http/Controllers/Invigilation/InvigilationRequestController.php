<?php

namespace App\Http\Controllers\Invigilation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invigilation\InvigilationRequestRequest;
use App\Http\Requests\Invigilation\InvigilationSubmissionRequest;
use App\Models\Invigilation\InvigilationRequest;
use App\Models\Invigilation\InvigilationRequestDepartment;
use App\Models\Invigilation\InvigilationSubmission;
use App\Services\Invigilation\InvigilationRequestService;
use App\Services\Invigilation\InvigilationSubmissionService;
use App\Services\User\DepartmentScopeService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Invigilation requests — the registrar asks, departments answer.
 *
 * Both sides live here because they are two halves of one exchange: the
 * registrar's list and the department's inbox are the same rows read from
 * different ends, and splitting them would mean two controllers maintaining
 * the same eager loads and the same scope rule.
 *
 * A department sees only its own shares. That is enforced by
 * `DepartmentScopeService`, the same mechanism confining schedules, so there is
 * one definition of "which departments are mine".
 */
class InvigilationRequestController extends Controller {

    /** Relations every read needs to render a request row. */
    private const EAGER = [
        'semester',
        'examType',
        'status',
        'requestedBy',
        'departments.department',
        'departments.submissions.instructor',
        'departments.submissions.submittedBy',
    ];

    /**
     * The same eager set, but with the department shares confined to the ones
     * the caller manages.
     *
     * Without this a head who is allowed to SEE a request also receives every
     * other department's quota and the names they submitted — the row is
     * theirs to answer, the rest of the ask is not their business.
     *
     * @param array<int, int>|null $scope null = unrestricted
     *
     * @return array
     */
    private static function scopedEager(?array $scope): array {
        if ($scope === null) {
            return self::EAGER;
        }

        return [
            'semester',
            'examType',
            'status',
            'requestedBy',
            'departments' => fn ($query) => $query->whereIn('department_id', $scope),
            'departments.department',
            'departments.submissions.instructor',
            'departments.submissions.submittedBy',
        ];
    }

    /**
     * List requests. A registrar sees all of them; a department head sees only
     * requests that ask something of a department they own.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeInvigilationRequest() && !isDropdownEnabled()) {
            return Response::_403();
        }

        // Managed, not merely readable: a request is addressed to a
        // department's head, and teaching in a department is not standing in
        // for it.
        $scope = app(DepartmentScopeService::class)->managedDepartmentIds();

        $requests = InvigilationRequest::query()
            ->with(self::scopedEager($scope))
            // null = unrestricted. An empty array is NOT the same: it means the
            // user owns no department and must see nothing.
            ->when($scope !== null, fn ($query) => $query->whereHas(
                'departments',
                fn ($share) => $share->whereIn('department_id', $scope ?? []),
            ))
            ->when($request->input('semester_id'), fn ($query) => $query->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->input('exam_type_lookup_value_id'), fn ($query) => $query->where('exam_type_lookup_value_id', (int) $request->input('exam_type_lookup_value_id')))
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($status) => $status->where('code', $request->input('status_code'))))
            ->when($request->input('department_id'), fn ($query) => $query->whereHas('departments', fn ($share) => $share->where('department_id', (int) $request->input('department_id'))))
            ->latest('id')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $requests->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => InvigilationRequest::extractPagination($requests),
        ]);
    }

    /**
     * Show one request by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeInvigilationRequest()) {
            return Response::_403();
        }

        $scope = app(DepartmentScopeService::class)->managedDepartmentIds();

        $invigilationRequest = InvigilationRequest::query()
            ->with(self::scopedEager($scope))
            ->when($scope !== null, fn ($query) => $query->whereHas(
                'departments',
                fn ($share) => $share->whereIn('department_id', $scope ?? []),
            ))
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$invigilationRequest) {
            return Response::_404(Message::get('invigilation_request_not_found'));
        }

        return Response::_200([
            'data' => $invigilationRequest->resource(),
        ]);
    }

    /**
     * Raise a request. It always starts at `draft`.
     *
     * @param \App\Http\Requests\Invigilation\InvigilationRequestRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(InvigilationRequestRequest $request): JsonResponse {
        try {
            $result = app(InvigilationRequestService::class)->createRequest($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_invigilation_request'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('invigilation_request_created_successfully'),
        ]);
    }

    /**
     * Revise a draft request.
     *
     * @param \App\Http\Requests\Invigilation\InvigilationRequestRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(InvigilationRequestRequest $request, $id): JsonResponse {
        $invigilationRequest = InvigilationRequest::with('status')->find($id);
        if (!$invigilationRequest) {
            return Response::_404(Message::get('invigilation_request_not_found'));
        }

        try {
            $result = app(InvigilationRequestService::class)->updateRequest($invigilationRequest, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_invigilation_request'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('invigilation_request_updated_successfully'),
        ]);
    }

    /**
     * Send a draft to its departments: `draft → sent`.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function send($id): JsonResponse {
        return $this->move($id, fn (InvigilationRequest $target) => app(InvigilationRequestService::class)->send($target), 'invigilation_request_sent_successfully');
    }

    /**
     * Close a sent request: `sent → closed`.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function close($id): JsonResponse {
        return $this->move($id, fn (InvigilationRequest $target) => app(InvigilationRequestService::class)->close($target), 'invigilation_request_closed_successfully');
    }

    /**
     * A department answers its share with people.
     *
     * @param \App\Http\Requests\Invigilation\InvigilationSubmissionRequest $request
     * @param int $shareId the `invigilation_request_departments` row
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(InvigilationSubmissionRequest $request, $shareId): JsonResponse {
        $share = InvigilationRequestDepartment::with(['request.status', 'submissions'])->find($shareId);
        if (!$share) {
            return Response::_404(Message::get('invigilation_request_not_found'));
        }

        // A department answers only its own ask, and only its head answers.
        if (!app(DepartmentScopeService::class)->manages((int) $share->department_id)) {
            return Response::_403();
        }

        try {
            $result = app(InvigilationSubmissionService::class)->submit(
                $share,
                $request->validated('instructor_ids'),
                $request->validated('remark'),
            );
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_submit_invigilators'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(['department', 'submissions.instructor'])->resource(),
            'message' => Message::get('invigilators_submitted_successfully'),
        ]);
    }

    /**
     * A department takes one submitted person back.
     *
     * @param int $submissionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw($submissionId): JsonResponse {
        if (!$this->userCanRespondToInvigilationRequest()) {
            return Response::_403();
        }

        $submission = InvigilationSubmission::with(['requestDepartment.request.status'])->find($submissionId);
        if (!$submission) {
            return Response::_404(Message::get('invigilation_submission_not_found'));
        }

        if (!app(DepartmentScopeService::class)->manages((int) $submission->requestDepartment?->department_id)) {
            return Response::_403();
        }

        try {
            $result = app(InvigilationSubmissionService::class)->withdraw($submission);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_withdraw_invigilator'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(['department', 'submissions.instructor'])->resource(),
            'message' => Message::get('invigilator_withdrawn_successfully'),
        ]);
    }

    /**
     * One guarded lifecycle move, with the permission and lookup checks every
     * one of them shares.
     *
     * @param int $id
     * @param callable $action
     * @param string $messageKey
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function move($id, callable $action, string $messageKey): JsonResponse {
        if (!$this->userCanSendInvigilationRequest()) {
            return Response::_403();
        }

        $invigilationRequest = InvigilationRequest::with(['status', 'departments'])->find($id);
        if (!$invigilationRequest) {
            return Response::_404(Message::get('invigilation_request_not_found'));
        }

        try {
            $result = $action($invigilationRequest);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_invigilation_request'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get($messageKey),
        ]);
    }
}
