<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campus\CampusRequest;
use App\Models\Physical\Campus;
use App\Services\Physical\CampusService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class CampusController extends Controller {

    /**
     * List campuses with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeCampus() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $isMain = $request->input('is_main');

        $campuses = Campus::query()
            ->with('user')
            ->withCount('buildings')
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'ilike', "%{$search}%")
                            ->orWhere('city', 'ilike', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('name', $search, true));
                    });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->when($isMain !== null, fn ($query) => $query->where('is_main', filter_var($isMain, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $campuses->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Campus::extractPagination($campuses),
        ]);
    }

    /**
     * Show a campus by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeCampus()) {
            return Response::_403();
        }

        $item = Campus::query()
            ->with('user')
            ->withCount('buildings')
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$item) {
            return Response::_404(Message::get('campus_not_found'));
        }

        return Response::_200([
            'data' => $item->resource(),
        ]);
    }

    /**
     * Create a campus.
     *
     * @param \App\Http\Requests\Campus\CampusRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CampusRequest $request): JsonResponse {
        try {
            $result = app(CampusService::class)->createCampus($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_campus'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('campus_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a campus.
     *
     * @param \App\Http\Requests\Campus\CampusRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CampusRequest $request, $id): JsonResponse {
        $campus = Campus::find($id);
        if (!$campus) {
            return Response::_404(Message::get('campus_not_found'));
        }

        try {
            $result = app(CampusService::class)->updateCampus($campus, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_campus'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->name__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('campus_updated_successfully', $bindings),
        ]);
    }

    /**
     * Soft delete a campus.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteCampus()) {
            return Response::_403();
        }

        $campus = Campus::find($id);
        if (!$campus) {
            return Response::_404(Message::get('campus_not_found'));
        }

        if ($campus->buildings()->exists()) {
            return Response::_422(Message::get('campus_has_buildings'));
        }

        $bindings = ['name' => $campus->name__localized];
        $campus->delete();

        return Response::_200([
            'message' => Message::get('campus_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a campus is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeCampusState()) {
            return Response::_403();
        }

        $campus = Campus::find($id);
        if (!$campus) {
            return Response::_404(Message::get('campus_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('campus') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($campus->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $campus->is_active = $isActive;
        $campus->save();

        $message = $isActive
            ? 'campus_activated'
            : 'campus_deactivated';

        return Response::_200([
            'data' => $campus->resource(),
            'message' => Message::get($message, ['name' => $campus->name__localized]),
        ]);
    }
}
