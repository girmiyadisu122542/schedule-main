<?php

namespace App\Http\Controllers\Building;

use App\Http\Controllers\Concerns\HandlesMasterDataImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Building\BuildingRequest;
use App\Http\Requests\Import\BuildingImportRequest;
use App\Models\Physical\Building;
use App\Services\Physical\BuildingService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\BuildingColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class BuildingController extends Controller {
    use HandlesMasterDataImportExport;

    /**
     * List buildings with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeBuilding() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $buildings = $this->filteredQuery($request)->paginate(static::getPerPage());

        return Response::_200([
            'data' => $buildings->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Building::extractPagination($buildings),
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
        $campusId = $request->input('campus_id');
        $isActive = $request->input('is_active');

        return Building::query()
            ->with(['campus', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($campusId, fn ($query) => $query->where('campus_id', (int) $campusId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at');
    }

    /**
     * Import buildings from a spreadsheet.
     *
     * Declared here rather than inherited so the route type-hints the
     * CONCRETE request — `ImportRequest` is abstract and the container
     * cannot build it.
     *
     * @param \App\Http\Requests\Import\BuildingImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(BuildingImportRequest $request): JsonResponse {
        return $this->handleImport($request);
    }

    /**
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    protected function columnMap(): AbstractColumnMap {
        return new BuildingColumnMap();
    }

    /**
     * @return bool
     */
    protected function canExportEntity(): bool {
        return $this->userCanExportBuilding();
    }

    /**
     * @return bool
     */
    protected function canImportEntity(): bool {
        return $this->userCanImportBuilding();
    }

    /**
     * Show a building by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeBuilding()) {
            return Response::_403();
        }

        $item = Building::query()
            ->with(['campus', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$item) {
            return Response::_404(Message::get('building_not_found'));
        }

        return Response::_200([
            'data' => $item->resource(),
        ]);
    }

    /**
     * Create a building.
     *
     * @param \App\Http\Requests\Building\BuildingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(BuildingRequest $request): JsonResponse {
        try {
            $result = app(BuildingService::class)->createBuilding($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_building'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('building_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a building.
     *
     * @param \App\Http\Requests\Building\BuildingRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(BuildingRequest $request, $id): JsonResponse {
        $building = Building::find($id);
        if (!$building) {
            return Response::_404(Message::get('building_not_found'));
        }

        try {
            $result = app(BuildingService::class)->updateBuilding($building, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_building'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('building_updated_successfully', $bindings),
        ]);
    }

    /**
     * Soft delete a building.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteBuilding()) {
            return Response::_403();
        }

        $building = Building::find($id);
        if (!$building) {
            return Response::_404(Message::get('building_not_found'));
        }

        $bindings = ['name' => $building->name__localized];
        $building->delete();

        return Response::_200([
            'message' => Message::get('building_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a building is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeBuildingState()) {
            return Response::_403();
        }

        $building = Building::find($id);
        if (!$building) {
            return Response::_404(Message::get('building_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('building') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($building->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $building->is_active = $isActive;
        $building->save();

        $message = $isActive
            ? 'building_activated'
            : 'building_deactivated';

        return Response::_200([
            'data' => $building->resource(),
            'message' => Message::get($message, ['name' => $building->name__localized]),
        ]);
    }
}
