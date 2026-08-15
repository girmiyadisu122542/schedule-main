<?php

namespace App\Http\Controllers\Semester;

use App\Http\Controllers\Controller;
use App\Http\Requests\Semester\SemesterRequest;
use App\Models\Academic\Semester;
use App\Rules\LookupValueOfType;
use App\Services\Academic\SemesterService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

/**
 * Semesters have no `is_active` and no `state` — there is deliberately no
 * changeState action. The only lifecycle move is `changeStatus`, which the
 * lookup engine's `lookup_transitions` rows guard (Final Schema.md §7).
 */
class SemesterController extends Controller {

    /**
     * List semesters with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeSemester() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $academicYearId = $request->input('academic_year_id');
        $statusCode = $request->input('status_code');
        $isCurrent = $request->input('is_current');

        $semesters = Semester::query()
            ->with(['academicYear', 'status', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->whereHas('academicYear', fn ($query) => $query->where('code', 'like', "%{$search}%"))
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', (int) $academicYearId))
            ->when($statusCode, fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $statusCode)))
            ->when($isCurrent !== null, fn ($query) => $query->where('is_current', filter_var($isCurrent, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('start_date')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $semesters->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Semester::extractPagination($semesters),
        ]);
    }

    /**
     * Show a semester by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeSemester()) {
            return Response::_403();
        }

        $semester = Semester::query()
            ->with(['academicYear', 'status', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$semester) {
            return Response::_404(Message::get('semester_not_found'));
        }

        return Response::_200([
            'data' => $semester->resource(),
        ]);
    }

    /**
     * Create a semester. It always starts at SEMESTER_STATUS `planning`.
     *
     * @param \App\Http\Requests\Semester\SemesterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(SemesterRequest $request): JsonResponse {
        try {
            $result = app(SemesterService::class)->createSemester($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_semester'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->displayLabel()];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('semester_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a semester. The status is not touched here.
     *
     * @param \App\Http\Requests\Semester\SemesterRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(SemesterRequest $request, $id): JsonResponse {
        $semester = Semester::find($id);
        if (!$semester) {
            return Response::_404(Message::get('semester_not_found'));
        }

        try {
            $result = app(SemesterService::class)->updateSemester($semester, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_semester'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->displayLabel()];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('semester_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete a semester. Hard delete — the table has no soft-delete column, and
     * the restrict FKs on offerings/schedules stop a semester that is in use.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteSemester()) {
            return Response::_403();
        }

        $semester = Semester::find($id);
        if (!$semester) {
            return Response::_404(Message::get('semester_not_found'));
        }

        $bindings = ['name' => $semester->displayLabel()];

        try {
            $semester->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('semester_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('semester_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Move a semester along the SEMESTER_STATUS lifecycle.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus($id): JsonResponse {
        if (!$this->userCanChangeSemesterStatus()) {
            return Response::_403();
        }

        $semester = Semester::find($id);
        if (!$semester) {
            return Response::_404(Message::get('semester_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'status_lookup_value_id' => [
                'required',
                'integer',
                new LookupValueOfType(SEMESTER_STATUS, 'invalid_semester_status'),
            ],
        ], Message::get('semester') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        try {
            $result = app(SemesterService::class)->changeStatus($semester, (int) request()->status_lookup_value_id);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_semester'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('semester_status_changed_successfully', [
                'name' => $result->displayLabel(),
                'status' => $result->status?->name__localized,
            ]),
        ]);
    }
}
