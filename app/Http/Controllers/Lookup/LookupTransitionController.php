<?php

namespace App\Http\Controllers\Lookup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lookup\LookupTransitionRequest;
use App\Models\Common\Lookup\LookupTransition;
use App\Models\Common\Lookup\LookupType;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Translation\Message;

class LookupTransitionController extends Controller {

    /**
     * Display all transitions for a lookup type.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeDynamicValue() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $lookupTypeId = $request->input('lookup_type_id');
        $state = $request->input('state');

        $transitions = LookupTransition::query()
            ->with([
                'fromValue',
                'toValue',
                'lookupType' => function ($query) {
                    $query
                        ->applyRoleBasedQuery(
                            permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                        );
                },
            ])
            ->whereHas('lookupType', function ($query) {
                $query
                    ->applyRoleBasedQuery(
                        permissionKey: PERMISSION_SEE_DYNAMIC_VALUE,
                    );
            })
            ->when($lookupTypeId, fn ($query) => $query->where('lookup_type_id', (int) $lookupTypeId))
            ->when($state !== null && $state !== '', fn ($query) => $query->where('state', (int) $state))
            ->orderByDesc('created_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $transitions->collection(isDropdownEnabled() ? 'dropdownFields' : null),
            'pagination' => LookupTransition::extractPagination($transitions),
        ]);
    }

    /**
     * Allowed transitions for a lookup type, as flat from/to code pairs. Dropdown-friendly
     * so a status-change popover can flow-gate its options without the heavier
     * dynamic-value permission.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forType(Request $request): JsonResponse {
        if (!$this->userCanSeeDynamicValue() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $typeCode = $request->input('type_code');

        if (!$typeCode) {
            return Response::_200([
                'data' => [],
            ]);
        }

        $transitions = LookupService::getTransitionsForType($typeCode);

        $data = $transitions
            ->map(fn ($transition) => [
                'id' => $transition->id,
                'from_code' => $transition->fromValue?->code,
                'to_code' => $transition->toValue?->code,
            ])
            ->filter(fn ($pair) => $pair['from_code'] && $pair['to_code'])
            ->values();

        return Response::_200([
            'data' => $data,
        ]);
    }

    /**
     * Create a new transition.
     *
     * @param \App\Http\Requests\Lookup\LookupTransitionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(LookupTransitionRequest $request): JsonResponse {
        if (!$this->userCanCreateDynamicValue()) {
            return Response::_403();
        }

        $validated = $request->validated();

        $lookupType = LookupType::query()
            ->applyRoleBasedQuery(
                permissionKey: PERMISSION_CREATE_DYNAMIC_VALUE,
            )
            ->find($validated['lookup_type_id']);

        if (!$lookupType) {
            return Response::_403(Message::get('lookup_type_not_accessible'));
        }

        $exists = LookupTransition::query()
            ->where('lookup_type_id', $validated['lookup_type_id'])
            ->where('from_value_id', $validated['from_value_id'])
            ->where('to_value_id', $validated['to_value_id'])
            ->exists();

        if ($exists) {
            return Response::_422(Message::get('lookup_transition_already_exists'));
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $transition = LookupTransition::create([
                'lookup_type_id' => $validated['lookup_type_id'],
                'from_value_id' => $validated['from_value_id'],
                'to_value_id' => $validated['to_value_id'],
                'state' => STATE_ACTIVE,
            ]);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_create_lookup_transition'));
        }

        return Response::_201([
            'data' => $transition->resource(),
            'message' => Message::get('lookup_transition_successfully_created'),
        ]);
    }

    /**
     * Delete a transition.
     *
     * @param int $transition
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $transition): JsonResponse {
        if (!$this->userCanDeleteDynamicValue()) {
            return Response::_403();
        }

        $trans = LookupTransition::query()
            ->with('lookupType', function ($query) {
                $query
                    ->applyRoleBasedQuery(
                        permissionKey: PERMISSION_DELETE_DYNAMIC_VALUE,
                    );
            })
            ->find($transition);

        if (!$trans) {
            return Response::_404(Message::get('lookup_transition_not_found'));
        }

        if ($trans->lookupType->is_system) {
            return Response::_422(Message::get('lookup_transition_is_system_cannot_delete'));
        }

        $trans->delete();

        return Response::_200([
            'message' => Message::get('lookup_transition_successfully_deleted'),
        ]);
    }
}
