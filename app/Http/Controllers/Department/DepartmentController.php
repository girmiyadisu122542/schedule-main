<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\DepartmentRequest;
use App\Models\Academic\Department;
use App\Services\Academic\DepartmentService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class DepartmentController extends Controller {

    /**
     * List departments with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeDepartment() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $collegeId = $request->input('college_id');
        $isActive = $request->input('is_active');

        $departments = Department::query()
            ->with(['college', 'head', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'ilike', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($collegeId, fn ($query) => $query->where('college_id', (int) $collegeId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $departments->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Department::extractPagination($departments),
        ]);
    }

    /**
     * Show a department by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeDepartment()) {
            return Response::_403();
        }

        $department = Department::query()
            ->with(['college', 'head', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$department) {
            return Response::_404(Message::get('department_not_found'));
        }

        return Response::_200([
            'data' => $department->resource(),
        ]);
    }

    /**
     * Create a department.
     *
     * @param \App\Http\Requests\Department\DepartmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(DepartmentRequest $request): JsonResponse {
        try {
            $result = app(DepartmentService::class)->createDepartment($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_department'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('department_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a department.
     *
     * @param \App\Http\Requests\Department\DepartmentRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(DepartmentRequest $request, $id): JsonResponse {
        $department = Department::find($id);
        if (!$department) {
            return Response::_404(Message::get('department_not_found'));
        }

        try {
            $result = app(DepartmentService::class)->updateDepartment($department, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_department'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('department_updated_successfully', $bindings),
        ]);
    }

    /**
     * Soft delete a department.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteDepartment()) {
            return Response::_403();
        }

        $department = Department::find($id);
        if (!$department) {
            return Response::_404(Message::get('department_not_found'));
        }

        $bindings = ['name' => $department->name__localized];
        $department->delete();

        return Response::_200([
            'message' => Message::get('department_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a department is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeDepartmentState()) {
            return Response::_403();
        }

        $department = Department::find($id);
        if (!$department) {
            return Response::_404(Message::get('department_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('department') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($department->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $department->is_active = $isActive;
        $department->save();

        $message = $isActive
            ? 'department_activated'
            : 'department_deactivated';

        return Response::_200([
            'data' => $department->resource(),
            'message' => Message::get($message, ['name' => $department->name__localized]),
        ]);
    }
}
