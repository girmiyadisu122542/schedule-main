<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Concerns\HandlesMasterDataImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\InstructorImportRequest;
use App\Http\Requests\Instructor\InstructorRequest;
use App\Models\People\Instructor;
use App\Services\People\InstructorService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\InstructorColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class InstructorController extends Controller {
    use HandlesMasterDataImportExport;

    /**
     * List instructors with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeInstructor() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $instructors = $this->filteredQuery($request)->paginate(static::getPerPage());

        return Response::_200([
            'data' => $instructors->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Instructor::extractPagination($instructors),
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

        return Instructor::query()
            ->with(['department', 'person', 'academicRank'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('employee_no', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('full_name', $search, true));
                    });
            })
            ->when($request->input('department_id'), fn ($query) => $query->where('department_id', (int) $request->input('department_id')))
            ->when($request->has('can_teach'), fn ($query) => $query->where('can_teach', $request->boolean('can_teach')))
            ->when($request->has('can_invigilate'), fn ($query) => $query->where('can_invigilate', $request->boolean('can_invigilate')))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at');
    }

    /**
     * Import instructors from a spreadsheet.
     *
     * Declared here rather than inherited so the route type-hints the
     * CONCRETE request — `ImportRequest` is abstract and the container
     * cannot build it.
     *
     * @param \App\Http\Requests\Import\InstructorImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(InstructorImportRequest $request): JsonResponse {
        return $this->handleImport($request);
    }

    /**
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    protected function columnMap(): AbstractColumnMap {
        return new InstructorColumnMap();
    }

    /**
     * @return bool
     */
    protected function canExportEntity(): bool {
        return $this->userCanExportInstructor();
    }

    /**
     * @return bool
     */
    protected function canImportEntity(): bool {
        return $this->userCanImportInstructor();
    }

    /**
     * Show a instructor by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeInstructor()) {
            return Response::_403();
        }

        $instructor = Instructor::query()
            ->with(['department', 'person', 'academicRank'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$instructor) {
            return Response::_404(Message::get('instructor_not_found'));
        }

        return Response::_200([
            'data' => $instructor->resource(),
        ]);
    }

    /**
     * Create a instructor.
     *
     * @param \App\Http\Requests\Instructor\InstructorRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(InstructorRequest $request): JsonResponse {
        try {
            $result = app(InstructorService::class)->createInstructor($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_instructor'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->full_name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('instructor_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a instructor.
     *
     * @param \App\Http\Requests\Instructor\InstructorRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(InstructorRequest $request, $id): JsonResponse {
        $instructor = Instructor::find($id);
        if (!$instructor) {
            return Response::_404(Message::get('instructor_not_found'));
        }

        try {
            $result = app(InstructorService::class)->updateInstructor($instructor, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_instructor'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->full_name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('instructor_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete a instructor.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteInstructor()) {
            return Response::_403();
        }

        $instructor = Instructor::find($id);
        if (!$instructor) {
            return Response::_404(Message::get('instructor_not_found'));
        }

        $bindings = ['name' => $instructor->full_name__localized];

        try {
            $instructor->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('instructor_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('instructor_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a instructor is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeInstructorState()) {
            return Response::_403();
        }

        $instructor = Instructor::find($id);
        if (!$instructor) {
            return Response::_404(Message::get('instructor_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('instructor') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($instructor->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $instructor->is_active = $isActive;
        $instructor->save();

        $message = $isActive
            ? 'instructor_activated'
            : 'instructor_deactivated';

        return Response::_200([
            'data' => $instructor->resource(),
            'message' => Message::get($message, ['name' => $instructor->full_name__localized]),
        ]);
    }
}
