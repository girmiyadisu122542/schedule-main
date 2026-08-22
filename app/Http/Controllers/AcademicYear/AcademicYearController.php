<?php

namespace App\Http\Controllers\AcademicYear;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYear\AcademicYearRequest;
use App\Models\Academic\AcademicYear;
use App\Services\Academic\AcademicYearService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Academic years have no `is_active` and no `state` — there is deliberately no
 * changeState action here (Final Schema.md §6).
 */
class AcademicYearController extends Controller {

    /**
     * List academic years with search.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeAcademicYear() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $isCurrent = $request->input('is_current');

        $academicYears = AcademicYear::query()
            ->with('user')
            ->when($search, fn ($query) => $query->where('code', 'like', "%{$search}%"))
            ->when($isCurrent !== null, fn ($query) => $query->where('is_current', filter_var($isCurrent, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('start_date')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $academicYears->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => AcademicYear::extractPagination($academicYears),
        ]);
    }

    /**
     * Show an academic year by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeAcademicYear()) {
            return Response::_403();
        }

        $academicYear = AcademicYear::query()
            ->with('user')
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$academicYear) {
            return Response::_404(Message::get('academic_year_not_found'));
        }

        return Response::_200([
            'data' => $academicYear->resource(),
        ]);
    }

    /**
     * Create an academic year.
     *
     * @param \App\Http\Requests\AcademicYear\AcademicYearRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AcademicYearRequest $request): JsonResponse {
        try {
            $result = app(AcademicYearService::class)->createAcademicYear($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_academic_year'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->code];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('academic_year_created_successfully', $bindings),
        ]);
    }

    /**
     * Update an academic year.
     *
     * @param \App\Http\Requests\AcademicYear\AcademicYearRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AcademicYearRequest $request, $id): JsonResponse {
        $academicYear = AcademicYear::find($id);
        if (!$academicYear) {
            return Response::_404(Message::get('academic_year_not_found'));
        }

        try {
            $result = app(AcademicYearService::class)->updateAcademicYear($academicYear, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_academic_year'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->code];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('academic_year_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete an academic year. Hard delete — the table has no soft-delete column,
     * and the restrict FKs on semesters/sections stop a year that is in use.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteAcademicYear()) {
            return Response::_403();
        }

        $academicYear = AcademicYear::find($id);
        if (!$academicYear) {
            return Response::_404(Message::get('academic_year_not_found'));
        }

        $bindings = ['name' => $academicYear->code];

        try {
            $academicYear->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('academic_year_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('academic_year_deleted_successfully', $bindings),
        ]);
    }
}
