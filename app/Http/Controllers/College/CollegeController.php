<?php

namespace App\Http\Controllers\College;

use App\Http\Controllers\Concerns\HandlesMasterDataImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\College\CollegeRequest;
use App\Http\Requests\Import\CollegeImportRequest;
use App\Models\Academic\College;
use App\Services\Academic\CollegeService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\CollegeColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class CollegeController extends Controller {
    use HandlesMasterDataImportExport;

    /**
     * List colleges with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeCollege() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $colleges = $this->filteredQuery($request)->paginate(static::getPerPage());

        return Response::_200([
            'data' => $colleges->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => College::extractPagination($colleges),
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

        return College::query()
            ->with(['dean', 'user'])
            ->withCount('departments')
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'ilike', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at');
    }

    /**
     * Import colleges from a spreadsheet.
     *
     * Declared here rather than inherited so the route type-hints the
     * CONCRETE request — `ImportRequest` is abstract and the container
     * cannot build it.
     *
     * @param \App\Http\Requests\Import\CollegeImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(CollegeImportRequest $request): JsonResponse {
        return $this->handleImport($request);
    }

    /**
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    protected function columnMap(): AbstractColumnMap {
        return new CollegeColumnMap();
    }

    /**
     * @return bool
     */
    protected function canExportEntity(): bool {
        return $this->userCanExportCollege();
    }

    /**
     * @return bool
     */
    protected function canImportEntity(): bool {
        return $this->userCanImportCollege();
    }

    /**
     * Show a college by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeCollege()) {
            return Response::_403();
        }

        $college = College::query()
            ->with(['dean', 'user'])
            ->withCount('departments')
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$college) {
            return Response::_404(Message::get('college_not_found'));
        }

        return Response::_200([
            'data' => $college->resource(),
        ]);
    }

    /**
     * Create a college.
     *
     * @param \App\Http\Requests\College\CollegeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CollegeRequest $request): JsonResponse {
        try {
            $result = app(CollegeService::class)->createCollege($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_college'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('college_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a college.
     *
     * @param \App\Http\Requests\College\CollegeRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CollegeRequest $request, $id): JsonResponse {
        $college = College::find($id);
        if (!$college) {
            return Response::_404(Message::get('college_not_found'));
        }

        try {
            $result = app(CollegeService::class)->updateCollege($college, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_college'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('college_updated_successfully', $bindings),
        ]);
    }

    /**
     * Soft delete a college.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteCollege()) {
            return Response::_403();
        }

        $college = College::find($id);
        if (!$college) {
            return Response::_404(Message::get('college_not_found'));
        }

        if ($college->departments()->exists()) {
            return Response::_422(Message::get('college_has_departments'));
        }

        $bindings = ['name' => $college->name__localized];
        $college->delete();

        return Response::_200([
            'message' => Message::get('college_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a college is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeCollegeState()) {
            return Response::_403();
        }

        $college = College::find($id);
        if (!$college) {
            return Response::_404(Message::get('college_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('college') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($college->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $college->is_active = $isActive;
        $college->save();

        $message = $isActive
            ? 'college_activated'
            : 'college_deactivated';

        return Response::_200([
            'data' => $college->resource(),
            'message' => Message::get($message, ['name' => $college->name__localized]),
        ]);
    }
}
