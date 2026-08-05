<?php

namespace App\Http\Controllers\Program;

use App\Http\Controllers\Controller;
use App\Http\Requests\Program\ProgramRequest;
use App\Models\Academic\Program;
use App\Services\Academic\ProgramService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class ProgramController extends Controller {

    /**
     * List programs with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeProgram() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $departmentId = $request->input('department_id');
        $isActive = $request->input('is_active');

        $programs = Program::query()
            ->with(['department', 'degreeLevel', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'ilike', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($departmentId, fn ($query) => $query->where('department_id', (int) $departmentId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $programs->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Program::extractPagination($programs),
        ]);
    }

    /**
     * Show a program by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeProgram()) {
            return Response::_403();
        }

        $program = Program::query()
            ->with(['department', 'degreeLevel', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$program) {
            return Response::_404(Message::get('program_not_found'));
        }

        return Response::_200([
            'data' => $program->resource(),
        ]);
    }

    /**
     * Create a program.
     *
     * @param \App\Http\Requests\Program\ProgramRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ProgramRequest $request): JsonResponse {
        try {
            $result = app(ProgramService::class)->createProgram($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_program'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('program_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a program.
     *
     * @param \App\Http\Requests\Program\ProgramRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProgramRequest $request, $id): JsonResponse {
        $program = Program::find($id);
        if (!$program) {
            return Response::_404(Message::get('program_not_found'));
        }

        try {
            $result = app(ProgramService::class)->updateProgram($program, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_program'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('program_updated_successfully', $bindings),
        ]);
    }

    /**
     * Soft delete a program.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteProgram()) {
            return Response::_403();
        }

        $program = Program::find($id);
        if (!$program) {
            return Response::_404(Message::get('program_not_found'));
        }

        $bindings = ['name' => $program->name__localized];
        $program->delete();

        return Response::_200([
            'message' => Message::get('program_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a program is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeProgramState()) {
            return Response::_403();
        }

        $program = Program::find($id);
        if (!$program) {
            return Response::_404(Message::get('program_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('program') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($program->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $program->is_active = $isActive;
        $program->save();

        $message = $isActive
            ? 'program_activated'
            : 'program_deactivated';

        return Response::_200([
            'data' => $program->resource(),
            'message' => Message::get($message, ['name' => $program->name__localized]),
        ]);
    }
}
