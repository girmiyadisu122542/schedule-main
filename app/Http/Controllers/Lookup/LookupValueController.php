<?php

namespace App\Http\Controllers\Lookup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lookup\LookupValueBulkRequest;
use App\Http\Requests\Lookup\LookupValueRequest;
use App\Http\Requests\Lookup\LookupValueStatusRequest;
use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
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

class LookupValueController extends Controller {

    /**
     * Display all lookup values.
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

        $typeCode = $request->input('type_code');
        if ($typeCode) {
            $values = LookupService::getValuesForType($typeCode);

            return Response::_200([
                'data' => $values->collection('idAndNameFields'),
            ]);
        }

        $state = $request->input('state');
        $lookupTypeId = $request->input('lookup_type_id');
        $search = $request->filled('search');

        $permissions = $isDropdownEnabled
            ? (getPermissionsFromRequest() ?: PERMISSION_SEE_DYNAMIC_VALUE)
            : PERMISSION_SEE_DYNAMIC_VALUE;

        if (!$lookupTypeId && $request->filled('type_code')) {
            $lookupTypeId = LookupService::getTypeByCode($request->input('type_code'))?->id;
        }

        $isDefault = !is_null($request->input('is_default'))
            ? $request->boolean('is_default')
            : null;

        $values = LookupValue::query()
            ->applyStatusBasedQuery(
                permissionKey: $permissions,
                isAll: $isDropdownEnabled || $canSeeDynamicValue,
                includePending: $canSeeDynamicValue,
                state: $isDropdownEnabled
                    ? STATE_ACTIVE
                    : $state
            )
            ->when($search, fn ($query) => $query->jsonbLangValueSearch('name', $request->input('search')))
            ->when($lookupTypeId, fn ($query) => $query->where('lookup_type_id', (int) $lookupTypeId))
            ->when(!is_null($isDefault), fn ($query) => $query->where('is_default', $isDefault))
            ->orderBy('order')
            ->orderByDesc('created_at')
            ->paginate(static::getPerPage());

        $fields = $isDropdownEnabled
            ? 'idAndNameFields'
            : null;

        return Response::_200([
            'data' => $values->collection($fields),
            'pagination' => LookupValue::extractPagination($values),
        ]);
    }

    /**
     * Create a new lookup value.
     *
     * @param \App\Http\Requests\Lookup\LookupValueRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(LookupValueRequest $request): JsonResponse {
        $canCreateDynamicValue = $this->userCanCreateDynamicValue();
        if (!$canCreateDynamicValue) {
            return Response::_403();
        }

        $validated = $request->validated();
        $language = getCurrentLanguage($request);

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $validated['code'] = generateCode(
                name: $validated['name'],
                format: CODE_FORMAT_UPPER_SNAKE,
                options: [
                    CODE_OPT_UNIQUE => true,
                    CODE_OPT_MODEL => LookupValue::class,
                ]
            );

            $validated['name'] = [$language => $validated['name']];
            $validated['user_id'] = Auth::id();
            $validated['state'] = STATE_ACTIVE;
            $validated['status_lookup_value_id'] = null;

            if (!$canCreateDynamicValue) {
                $validated['status_lookup_value_id'] = LookupService::getValueByCode(
                    typeCode: LOOKUP_VALUE_STATUS,
                    valueCode: LOOKUP_VALUE_STATUS_PENDING,
                    needId: true
                );
            }

            if (!empty($validated['is_default'])) {
                LookupValue::query()
                    ->applyStatusBasedQuery(
                        permissionKey: PERMISSION_UPDATE_DYNAMIC_VALUE,
                        isAll: true,
                        includePending: $this->userCanSeeDynamicValue(),
                    )
                    ->where('lookup_type_id', $validated['lookup_type_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $lookupValue = LookupValue::create($validated);
            $bindings = ['name' => $lookupValue->name_localized];

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_create_lookup_value'));
        }

        return Response::_201([
            'data' => $lookupValue->resource(),
            'message' => Message::get('lookup_value_successfully_created', $bindings),
        ]);
    }

    /**
     * Create multiple lookup values at once.
     *
     * @param \App\Http\Requests\Lookup\LookupValueBulkRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeBulk(LookupValueBulkRequest $request): JsonResponse {
        $canCreateDynamicValue = $this->userCanCreateDynamicValue();
        if (!$canCreateDynamicValue) {
            return Response::_403();
        }

        $validated = $request->validated();
        $language = getCurrentLanguage($request);
        $lookupTypeId = $validated['lookup_type_id'];
        $values = $validated['values'];
        $createdValues = [];
        $hasDefault = false;
        $statusValueId = null;

        if (!$canCreateDynamicValue) {
            $statusValueId = LookupService::getValueByCode(
                typeCode: LOOKUP_VALUE_STATUS,
                valueCode: LOOKUP_VALUE_STATUS_PENDING,
                needId: true
            );
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($values as $valueData) {
                if (!empty($valueData['is_default'])) {
                    $hasDefault = true;
                    break;
                }
            }

            if ($hasDefault) {
                LookupValue::query()
                    ->applyStatusBasedQuery(
                        permissionKey: PERMISSION_UPDATE_DYNAMIC_VALUE,
                        isAll: true,
                        includePending: $this->userCanSeeDynamicValue(),
                    )
                    ->where('lookup_type_id', $lookupTypeId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            foreach ($values as $valueData) {
                $valueData['code'] = generateCode(
                    name: $valueData['name'],
                    format: CODE_FORMAT_UPPER_SNAKE,
                    options: [
                        CODE_OPT_UNIQUE => true,
                        CODE_OPT_MODEL => LookupValue::class,
                    ]
                );
                $lookupValue = LookupValue::create([
                    'lookup_type_id' => $lookupTypeId,
                    'name' => [$language => $valueData['name']],
                    'code' => $valueData['code'],
                    'color' => $valueData['color'] ?? null,
                    'icon' => $valueData['icon'] ?? null,
                    'order' => $valueData['order'] ?? 0,
                    'is_default' => $valueData['is_default'] ?? false,
                    'status_lookup_value_id' => $statusValueId,
                    'user_id' => Auth::id(),
                    'state' => STATE_ACTIVE,
                ]);

                $createdValues[] = $lookupValue;
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_create_lookup_values'));
        }

        return Response::_201([
            'message' => Message::get('lookup_values_successfully_created'),
        ]);
    }

    /**
     * Update an existing lookup value.
     *
     * @param \App\Http\Requests\Lookup\LookupValueRequest $request
     * @param int $value
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(LookupValueRequest $request, int $value): JsonResponse {
        $canUpdateDynamicValue = $this->userCanUpdateDynamicValue();
        $canSeeDynamicValue = $this->userCanSeeDynamicValue();
        if (!$canUpdateDynamicValue) {
            return Response::_403();
        }

        $lookupValue = LookupValue::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_UPDATE_DYNAMIC_VALUE,
                isAll: $canUpdateDynamicValue,
                includePending: $canSeeDynamicValue,
            )
            ->find($value);

        if (!$lookupValue) {
            return Response::_404(Message::get('lookup_value_not_found'));
        }

        $validated = $request->validated();
        $language = getCurrentLanguage($request);

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $validated['name'] = isset($validated['name'])
                ? updateLangField($lookupValue->name, $language, $validated['name'])
                : $lookupValue->name;
            $validated['user_id'] = Auth::id();

            if (!empty($validated['is_default'])) {
                LookupValue::query()
                    ->applyStatusBasedQuery(
                        permissionKey: PERMISSION_UPDATE_DYNAMIC_VALUE,
                        isAll: true,
                        includePending: $canSeeDynamicValue,
                    )
                    ->where('lookup_type_id', $lookupValue->lookup_type_id)
                    ->where('id', '!=', $lookupValue->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $lookupValue->update($validated);
            $bindings = ['name' => $lookupValue->name_localized];

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_update_lookup_value'));
        }

        return Response::_200([
            'data' => $lookupValue->resource(),
            'message' => Message::get('lookup_value_successfully_updated', $bindings),
        ]);
    }

    /**
     * Delete a lookup value.
     *
     * @param int $value
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $value): JsonResponse {
        $canDeleteDynamicValue = $this->userCanDeleteDynamicValue();
        if (!$canDeleteDynamicValue) {
            return Response::_403();
        }

        $lookupValue = LookupValue::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: $canDeleteDynamicValue,
                includePending: $this->userCanSeeDynamicValue(),
            )
            ->find($value);

        if (!$lookupValue) {
            return Response::_404(Message::get('lookup_value_not_found'));
        }

        $lookupValue->delete();

        return Response::_200([
            'message' => Message::get('lookup_value_successfully_deleted'),
        ]);
    }

    /**
     * Change the state of a lookup value.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $value
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState(Request $request, int $value): JsonResponse {
        $canChangeDynamicValueState = $this->userCanChangeDynamicValueState();
        if (!$canChangeDynamicValueState) {
            return Response::_403();
        }

        $lookupValue = LookupValue::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: $canChangeDynamicValueState,
                includePending: $this->userCanSeeDynamicValue(),
            )
            ->find($value);

        if (!$lookupValue) {
            return Response::_404(Message::get('lookup_value_not_found'));
        }

        $rules = ['state' => ['required', State::ruleIn()]];
        $validator = Validator::make($request->all(), $rules);
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        if ($lookupValue->state == $request->state) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $lookupValue->state = $request->state;
        $lookupValue->save();

        $message = $request->state == STATE_ACTIVE
            ? 'lookup_value_successfully_activated'
            : 'lookup_value_successfully_deactivated';
        $bindings = ['name' => $lookupValue->name_localized];

        return Response::_200([
            'data' => $lookupValue->resource(),
            'message' => Message::get($message, $bindings),
        ]);
    }

    /**
     * Reorder lookup values within a type.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $lookupTypeId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(Request $request, int $lookupTypeId): JsonResponse {
        if (!$this->userCanUpdateDynamicValue()) {
            return Response::_403();
        }

        $canSeeDynamicValue = $this->userCanSeeDynamicValue();

        $type = LookupType::find($lookupTypeId);
        if (!$type) {
            return Response::_404(Message::get('lookup_type_not_found'));
        }

        $rules = [
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', LookupValue::exists()],
        ];
        $validator = Validator::make($request->all(), $rules);
        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($request->order as $position => $valueId) {
                LookupValue::query()
                    ->applyStatusBasedQuery(
                        permissionKey: PERMISSION_UPDATE_DYNAMIC_VALUE,
                        isAll: true,
                        includePending: $canSeeDynamicValue,
                    )
                    ->where('id', $valueId)
                    ->where('lookup_type_id', $type->id)
                    ->update(['order' => $position + 1]);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_reorder_lookup_values'));
        }

        return Response::_200([
            'message' => Message::get('lookup_values_reordered'),
        ]);
    }

    /**
     * Set the status lookup value for a lookup value.
     *
     * @param \App\Http\Requests\Lookup\LookupValueStatusRequest $request
     * @param int $lookupValueId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus(LookupValueStatusRequest $request, int $lookupValueId): JsonResponse {
        $lookupValue = LookupValue::query()
            ->applyStatusBasedQuery(
                permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                isAll: true,
                includePending: $this->userCanSeeDynamicValue(),
            )
            ->find($lookupValueId);

        if (!$lookupValue) {
            return Response::_404(Message::get('lookup_value_not_found'));
        }

        $statusValueId = $request->input('status_lookup_value_id');

        $lookupValue->status_lookup_value_id = $statusValueId;
        $lookupValue->save();

        return Response::_200([
            'message' => Message::get('lookup_value_status_updated'),
        ]);
    }
}
