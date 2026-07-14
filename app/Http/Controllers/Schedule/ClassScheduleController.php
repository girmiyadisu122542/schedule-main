<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ClassScheduleRequest;
use App\Models\Schedule\ClassSchedule;
use App\Services\Schedule\ClassScheduleService;
use Helper\Response\Response;
use Helper\Type\State\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class ClassScheduleController extends Controller {

    /**
     * List class / exam schedules with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeClassSchedule() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $scheduleType = $request->input('schedule_type');
        $dayOfWeek = $request->input('day_of_week');

        $schedules = ClassSchedule::query()
            ->with('user')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'ilike', "%{$search}%")
                          ->orWhere('room', 'ilike', "%{$search}%")
                          ->orWhere('section', 'ilike', "%{$search}%")
                          ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                });
            })
            ->when($scheduleType, fn ($query) => $query->where('schedule_type', (int) $scheduleType))
            ->when($dayOfWeek, fn ($query) => $query->where('day_of_week', (int) $dayOfWeek))
            ->latest('updated_at')
            ->paginate(static::getPerPage($request->input('limit')));

        return Response::_200([
            'data' => $schedules->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => ClassSchedule::extractPagination($schedules),
        ]);
    }

    /**
     * Show a schedule by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::query()
            ->with('user')
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
     * Create a schedule entry.
     *
     * @param \App\Http\Requests\Schedule\ClassScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ClassScheduleRequest $request): JsonResponse {
        try {
            $result = app(ClassScheduleService::class)->createSchedule($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('class_schedule_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a schedule entry.
     *
     * @param \App\Http\Requests\Schedule\ClassScheduleRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ClassScheduleRequest $request, $id): JsonResponse {
        $schedule = ClassSchedule::find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        try {
            $result = app(ClassScheduleService::class)->updateSchedule($schedule, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_class_schedule'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('class_schedule_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete a schedule entry (soft delete).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteClassSchedule()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        $bindings = ['name' => $schedule->name__localized];
        $schedule->delete();

        return Response::_200([
            'message' => Message::get('class_schedule_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a schedule entry state.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeClassScheduleState()) {
            return Response::_403();
        }

        $schedule = ClassSchedule::find($id);
        if (!$schedule) {
            return Response::_404(Message::get('class_schedule_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'state' => ['required', 'integer', State::ruleIn()],
        ], Message::get('class_schedule') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($schedule->state == request()->state) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $schedule->state = (int) request()->state;
        $schedule->save();

        $message = request()->state == STATE_ACTIVE
            ? 'class_schedule_activated'
            : 'class_schedule_deactivated';

        return Response::_200([
            'data' => $schedule->resource(),
            'message' => Message::get($message, ['name' => $schedule->name__localized]),
        ]);
    }
}
