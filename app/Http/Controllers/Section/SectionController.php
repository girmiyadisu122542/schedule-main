<?php

namespace App\Http\Controllers\Section;

use App\Http\Controllers\Concerns\HandlesMasterDataImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\SectionImportRequest;
use App\Http\Requests\Section\SectionRequest;
use App\Models\Academic\Section;
use App\Services\Academic\SectionService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\SectionColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class SectionController extends Controller {
    use HandlesMasterDataImportExport;

    /**
     * List sections with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeSection() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $sections = $this->filteredQuery($request)->paginate(static::getPerPage());

        return Response::_200([
            'data' => $sections->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Section::extractPagination($sections),
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

        return Section::query()
            ->with(['program', 'academicYear', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('label', 'like', "%{$search}%")
                            ->orWhereHas('program', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                    });
            })
            ->when($request->input('program_id'), fn ($query) => $query->where('program_id', (int) $request->input('program_id')))
            ->when($request->input('academic_year_id'), fn ($query) => $query->where('academic_year_id', (int) $request->input('academic_year_id')))
            ->when($request->input('year_level'), fn ($query) => $query->where('year_level', (int) $request->input('year_level')))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at');
    }

    /**
     * Import sections from a spreadsheet.
     *
     * Declared here rather than inherited so the route type-hints the
     * CONCRETE request — `ImportRequest` is abstract and the container
     * cannot build it.
     *
     * @param \App\Http\Requests\Import\SectionImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(SectionImportRequest $request): JsonResponse {
        return $this->handleImport($request);
    }

    /**
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    protected function columnMap(): AbstractColumnMap {
        return new SectionColumnMap();
    }

    /**
     * @return bool
     */
    protected function canExportEntity(): bool {
        return $this->userCanExportSection();
    }

    /**
     * @return bool
     */
    protected function canImportEntity(): bool {
        return $this->userCanImportSection();
    }

    /**
     * Show a section by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeSection()) {
            return Response::_403();
        }

        $section = Section::query()
            ->with(['program', 'academicYear', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$section) {
            return Response::_404(Message::get('section_not_found'));
        }

        return Response::_200([
            'data' => $section->resource(),
        ]);
    }

    /**
     * Create a section.
     *
     * @param \App\Http\Requests\Section\SectionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(SectionRequest $request): JsonResponse {
        try {
            $result = app(SectionService::class)->createSection($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_section'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->displayLabel()];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('section_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a section.
     *
     * @param \App\Http\Requests\Section\SectionRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(SectionRequest $request, $id): JsonResponse {
        $section = Section::find($id);
        if (!$section) {
            return Response::_404(Message::get('section_not_found'));
        }

        try {
            $result = app(SectionService::class)->updateSection($section, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_section'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->displayLabel()];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('section_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete a section.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteSection()) {
            return Response::_403();
        }

        $section = Section::find($id);
        if (!$section) {
            return Response::_404(Message::get('section_not_found'));
        }

        $bindings = ['name' => $section->displayLabel()];

        try {
            $section->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('section_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('section_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a section is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeSectionState()) {
            return Response::_403();
        }

        $section = Section::find($id);
        if (!$section) {
            return Response::_404(Message::get('section_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('section') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($section->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $section->is_active = $isActive;
        $section->save();

        $message = $isActive
            ? 'section_activated'
            : 'section_deactivated';

        return Response::_200([
            'data' => $section->resource(),
            'message' => Message::get($message, ['name' => $section->displayLabel()]),
        ]);
    }
}
