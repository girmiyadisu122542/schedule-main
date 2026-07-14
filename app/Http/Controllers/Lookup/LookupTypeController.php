<?php

namespace App\Http\Controllers\Lookup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lookup\LookupTypeRequest;
use App\Http\Requests\Lookup\LookupTypeStatusRequest;
use App\Models\Common\Lookup\LookupType;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Helper\Type\State\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class LookupTypeController extends Controller {

    /**
     * Display all lookup types.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        $isDropdownEnabled = isDropdownEnabled();
        $canSeeDynamicValue = $this->userCanSeeDynamicValue();
        if (!$canSeeDynamicValue && !$isDropdownEnabled) {
            return Response::_403();
        }

        $state = $request->input('state');
        $search = $request->filled('search');

        $permissions = $isDropdownEnabled
            ? (getPermissionsFromRequest() ?: PERMISSION_SEE_DYNAMIC_VALUE)
            : PERMISSION_SEE_DYNAMIC_VALUE;

        $isSystem = !is_null($request->input('is_system'))
            ? $request->boolean('is_system')
            : null;

        $lookupTypes = LookupType::query()
            ->applyStatusBasedQuery(
                permissionKey: $permissions,
                isAll: $isDropdownEnabled || $canSeeDynamicValue,
                includePending: $canSeeDynamicValue,
                state: $isDropdownEnabled
                    ? STATE_ACTIVE
                    : $state
            )
            ->when($search, fn ($query) => $query->jsonbLangValueSearch('name', $search))
            ->when(!is_null($isSystem), fn ($query) => $query->where('is_system', $isSystem))
            ->withCount('values')
            ->orderByDesc('created_at')
            ->paginate(static::getPerPage());

        $fields = $isDropdownEnabled
            ? 'dropdownFields'
            : null;

        return Response::_200([
            'data' => $lookupTypes->collection($fields),
            'pagination' => LookupType::extractPagination($lookupTypes),
        ]);
    }

    /**
     * Create a new lookup type.
     *
     * @param \App\Http\Requests\Lookup\LookupTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(LookupTypeRequest $request): JsonResponse {
        if (!$this->userCanCreateDynamicValue()) {
            return Response::_403();
        }

        $validated = $request->validated();
        $language = getCurrentLanguage($request);
        $canCreate = $this->userCanCreateDynamicValue();

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $validated['code'] = generateCode(
                name: $validated['name'],
                format: CODE_FORMAT_UPPER_SNAKE,
                options: [
                    CODE_OPT_UNIQUE => true,
                    CODE_OPT_MODEL => LookupType::class,
                ]
            );

            $validated['name'] = [$language => $validated['name']];
            $validated['description'] = [$language => $validated['description'] ?? ''];
            $validated['is_system'] = $canCreate
                ? $request->boolean('is_system', false)
                : false;
            $validated['status_lookup_value_id'] = $canCreate
                ? null
                : LookupService::getValueByCode(
                    typeCode: LOOKUP_VALUE_STATUS,
                    valueCode: LOOKUP_VALUE_STATUS_PENDING,
                    needId: true
                );
            $validated['user_id'] = Auth::id();
            $validated['state'] = STATE_ACTIVE;

            $lookupType = LookupType::create($validated);
            $bindings = ['name' => $lookupType->name_localized,];

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_create_lookup_type'));
        }

        return Response::_201([
            'data' => $lookupType->resource(),
            'message' => Message::get('lookup_type_successfully_created', $bindings),
        ]);
    }

    /**
     * Update an existing lookup type.
     *
     * @param \App\Http\Requests\Lookup\LookupTypeRequest $request
     * @param int $lookupType
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(LookupTypeRequest $request, int $lookupType): JsonResponse {
        $canUpdateDynamicValue = $this->userCanUpdateDynamicValue();
        if (!$canUpdateDynamicValue) {
            return Response::_403();
        }

        $type = LookupType::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: $canUpdateDynamicValue,
                includePending: $canUpdateDynamicValue
            )
            ->find($lookupType);
        if (!$type) {
            return Response::_404(Message::get('lookup_type_not_found'));
        }

        $validated = $request->validated();
        $language = getCurrentLanguage($request);

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $validated['name'] = isset($validated['name'])
                ? updateLangField($type->name, $language, $validated['name'])
                : $type->name;
            $validated['description'] = isset($validated['description'])
                ? updateLangField($type->description, $language, $validated['description'])
                : $type->description;
            $validated['user_id'] = Auth::id();

            $type->update($validated);
            $bindings = ['name' => $type->name_localized,];

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_update_lookup_type'));
        }

        return Response::_200([
            'data' => $type->resource(),
            'message' => Message::get('lookup_type_successfully_updated', $bindings),
        ]);
    }

    /**
     * Delete a lookup type (only if not system and has no values).
     *
     * @param int $lookupType
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $lookupType): JsonResponse {
        $canDeleteDynamicValue = $this->userCanDeleteDynamicValue();
        if (!$canDeleteDynamicValue) {
            return Response::_403();
        }

        $type = LookupType::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: $canDeleteDynamicValue,
                includePending: $canDeleteDynamicValue
            )
            ->find($lookupType);
        if (!$type) {
            return Response::_404(Message::get('lookup_type_not_found'));
        }

        if ($type->is_system) {
            return Response::_422(Message::get('lookup_type_is_system_cannot_delete'));
        }

        $type->delete();

        return Response::_200([
            'message' => Message::get('lookup_type_successfully_deleted'),
        ]);
    }

    /**
     * Change the state of a lookup type.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $lookupType
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState(Request $request, int $lookupType): JsonResponse {
        $canChangeDynamicValueState = $this->userCanChangeDynamicValueState();
        if (!$canChangeDynamicValueState) {
            return Response::_403();
        }

        $type = LookupType::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: $canChangeDynamicValueState,
                includePending: $canChangeDynamicValueState
            )
            ->find($lookupType);
        if (!$type) {
            return Response::_404(Message::get('lookup_type_not_found'));
        }

        $rules = ['state' => ['required', State::ruleIn()]];

        $validator = Validator::make($request->all(), $rules);
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($type->state == $request->state) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $type->state = $request->state;
        $type->save();

        $message = $request->state == STATE_ACTIVE
            ? 'lookup_type_successfully_activated'
            : 'lookup_type_successfully_deactivated';

        return Response::_200([
            'data' => $type->resource(),
            'message' => Message::get($message, ['name' => $type->name_localized]),
        ]);
    }

    /**
     * Set the status lookup value for a lookup type.
     *
     * @param \App\Http\Requests\Lookup\LookupTypeStatusRequest $request
     * @param int $lookupType
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setStatus(LookupTypeStatusRequest $request, int $lookupType): JsonResponse {
        $type = LookupType::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: true
            )
            ->find($lookupType);
        if (!$type) {
            return Response::_404(Message::get('lookup_type_not_found'));
        }

        $statusValueId = $request->input('status_lookup_value_id');

        $type->status_lookup_value_id = $statusValueId;
        $type->save();

        return Response::_200([
            'message' => Message::get('lookup_type_status_updated'),
        ]);
    }

    /**
     * get type values for dropdown
     *
     * @param int $lookupType
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTypeValues(int $lookupType): JsonResponse {
        $isDropdownEnabled = isDropdownEnabled();
        $canSeeDynamicValue = $this->userCanSeeDynamicValue();
        if (!$canSeeDynamicValue && !$isDropdownEnabled) {
            return Response::_403();
        }

        $type = LookupType::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: $isDropdownEnabled || $canSeeDynamicValue
            )
            ->find($lookupType);

        if (!$type) {
            return Response::_404(Message::get('lookup_type_not_found'));
        }

        $values = $type
            ->values()
            ->where('state', STATE_ACTIVE)
            ->orderBy('created_at')
            ->get();

        $fields = $isDropdownEnabled
            ? 'idAndNameFields'
            : null;

        return Response::_200([
            'data' => $values->collection($fields),
        ]);
    }
}
