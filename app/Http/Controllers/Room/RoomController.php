<?php

namespace App\Http\Controllers\Room;

use App\Http\Controllers\Concerns\HandlesMasterDataImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\RoomImportRequest;
use App\Http\Requests\Room\RoomRequest;
use App\Models\Physical\Room;
use App\Services\Physical\RoomService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\RoomColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class RoomController extends Controller {
    use HandlesMasterDataImportExport;

    /**
     * List rooms with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeRoom() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $rooms = $this->filteredQuery($request)->paginate(static::getPerPage());

        return Response::_200([
            'data' => $rooms->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Room::extractPagination($rooms),
        ]);
    }

    /**
     * The filtered builder behind BOTH `index` and `export`.
     *
     * Export honours whatever the user has filtered to, and it does so by
     * calling this — the filter logic is defined once, here.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function filteredQuery(Request $request) {
        $search = $request->input('search');
        $isActive = $request->input('is_active');

        return Room::query()
            ->with(['building.campus', 'roomType', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($request->input('building_id'), fn ($query) => $query->where('building_id', (int) $request->input('building_id')))
            ->when($request->input('room_type_lookup_value_id'), fn ($query) => $query->where('room_type_lookup_value_id', (int) $request->input('room_type_lookup_value_id')))
            ->when($request->has('is_exam_venue'), fn ($query) => $query->where('is_exam_venue', $request->boolean('is_exam_venue')))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at');
    }

    /**
     * Import rooms from a spreadsheet.
     *
     * Declared here rather than inherited so the route type-hints the
     * CONCRETE request — `ImportRequest` is abstract and the container
     * cannot build it.
     *
     * @param \App\Http\Requests\Import\RoomImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(RoomImportRequest $request): JsonResponse {
        return $this->handleImport($request);
    }

    /**
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    protected function columnMap(): AbstractColumnMap {
        return new RoomColumnMap();
    }

    /**
     * @return bool
     */
    protected function canExportEntity(): bool {
        return $this->userCanExportRoom();
    }

    /**
     * @return bool
     */
    protected function canImportEntity(): bool {
        return $this->userCanImportRoom();
    }

    /**
     * Show a room by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeRoom()) {
            return Response::_403();
        }

        $room = Room::query()
            ->with(['building.campus', 'roomType', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$room) {
            return Response::_404(Message::get('room_not_found'));
        }

        return Response::_200([
            'data' => $room->resource(),
        ]);
    }

    /**
     * Create a room.
     *
     * @param \App\Http\Requests\Room\RoomRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(RoomRequest $request): JsonResponse {
        try {
            $result = app(RoomService::class)->createRoom($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_room'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized ?: $result->code];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('room_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a room.
     *
     * @param \App\Http\Requests\Room\RoomRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(RoomRequest $request, $id): JsonResponse {
        $room = Room::find($id);
        if (!$room) {
            return Response::_404(Message::get('room_not_found'));
        }

        try {
            $result = app(RoomService::class)->updateRoom($room, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_room'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized ?: $result->code];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('room_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete a room.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteRoom()) {
            return Response::_403();
        }

        $room = Room::find($id);
        if (!$room) {
            return Response::_404(Message::get('room_not_found'));
        }

        $bindings = ['name' => $room->name__localized ?: $room->code];

        try {
            $room->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('room_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('room_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a room is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeRoomState()) {
            return Response::_403();
        }

        $room = Room::find($id);
        if (!$room) {
            return Response::_404(Message::get('room_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('room') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($room->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $room->is_active = $isActive;
        $room->save();

        $message = $isActive
            ? 'room_activated'
            : 'room_deactivated';

        return Response::_200([
            'data' => $room->resource(),
            'message' => Message::get($message, ['name' => $room->name__localized ?: $room->code]),
        ]);
    }
}
