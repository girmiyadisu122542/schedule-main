<?php

namespace App\Http\Controllers\Invigilation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invigilation\InvigilatorAvailabilityRequest;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Services\Invigilation\InvigilatorAvailabilityService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Availability windows the department offers.
 *
 * There is no update action, and no state or status: a window is a positive
 * statement that either exists or does not. A wrong one is deleted and
 * re-submitted.
 */
class InvigilatorAvailabilityController extends Controller {

    /** Relations every read needs to render an availability row. */
    private const EAGER = ['instructor', 'semester', 'submitter'];

    /**
     * List availability windows with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeInvigilatorAvailability() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');

        $availabilities = InvigilatorAvailability::query()
            ->with(self::EAGER)
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->whereHas('instructor', fn ($query) => $query->where('employee_no', 'ilike', "%{$search}%"))
                            ->orWhereHas('instructor', fn ($query) => $query->jsonbLangValueSearch('full_name', $search, true));
                    });
            })
            ->when($request->input('instructor_id'), fn ($query) => $query->where('instructor_id', (int) $request->input('instructor_id')))
            ->when($request->input('semester_id'), fn ($query) => $query->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->input('available_date'), fn ($query) => $query->whereDate('available_date', $request->input('available_date')))
            // A roster reads by window, not by edit time.
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $availabilities->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => InvigilatorAvailability::extractPagination($availabilities),
        ]);
    }

    /**
     * Record one availability window.
     *
     * @param \App\Http\Requests\Invigilation\InvigilatorAvailabilityRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(InvigilatorAvailabilityRequest $request): JsonResponse {
        try {
            $result = app(InvigilatorAvailabilityService::class)->createAvailability($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_submit_availability'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(self::EAGER)->resource(),
            'message' => Message::get('availability_submitted_successfully', ['name' => $result->displayLabel()]),
        ]);
    }

    /**
     * Withdraw one availability window.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteInvigilatorAvailability()) {
            return Response::_403();
        }

        $availability = InvigilatorAvailability::with('instructor')->find($id);
        if (!$availability) {
            return Response::_404(Message::get('availability_not_found'));
        }

        $bindings = ['name' => $availability->displayLabel()];

        try {
            $availability->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('availability_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('availability_deleted_successfully', $bindings),
        ]);
    }
}
