<?php

namespace App\Services\Lookup;

use App\Models\Common\Lookup\LookupTransition;
use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use Illuminate\Support\Collection;

class LookupService {

    /**
     * Get all lookup types for a model
     *
     * @param string $model
     * @return \Illuminate\Support\Collection|LookupType|int|null
     */
    public static function getTypesForModel(string $model, bool $isSingle = false, bool $needId = false): Collection|LookupType|int|null {
        if ($isSingle) {
            $lookupType = LookupType::query()
                ->where('state', STATE_ACTIVE)
                ->where(function ($query) use ($model) {
                    $query
                        ->whereJsonContains('applies_to_model', $model)
                        ->orWhereNull('applies_to_model');
                })
                ->first();

            if ($needId && $lookupType) {
                return $lookupType->id;
            }

            return $lookupType;
        }

        return LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('state', STATE_ACTIVE)
            ->where(function ($query) use ($model) {
                $query
                    ->whereJsonContains('applies_to_model', $model)
                    ->orWhereNull('applies_to_model');
            })
            ->get();
    }

    /**
     * Get all lookup values for a specific type code
     *
     * @param string $typeCode
     * @return \Illuminate\Support\Collection
     */
    public static function getValuesForType(string $typeCode): Collection {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return collect();
        }

        return LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(isAll: true)
            ->where('lookup_type_id', $type->id)
            ->where('state', STATE_ACTIVE)
            ->orderBy('order')
            ->get();
    }

    /**
     * Get all transitions for a specific type code
     *
     * @param string $typeCode
     * @return \Illuminate\Support\Collection
     */
    public static function getTransitionsForType(string $typeCode): Collection {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return collect();
        }

        return LookupTransition::query()
            ->with(['fromValue', 'toValue'])
            ->where('lookup_type_id', $type->id)
            ->get();
    }

    /**
     * Get complete lookup data for a model (types, values, transitions)
     *
     * @param string $model
     * @return array
     */
    public static function getCompleteDataForModel(string $model): array {
        $types = self::getTypesForModel($model);
        $data = [];

        foreach ($types as $type) {
            $data[$type->code] = [
                'type' => $type,
                'values' => self::getValuesForType($type->code),
                'transitions' => self::getTransitionsForType($type->code),
            ];
        }

        return $data;
    }

    /**
     * Get allowed transitions from a specific value
     *
     * @param string $typeCode
     * @param string $fromValueCode
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getAllowedTransitions(string $typeCode, string $fromValueCode): Collection {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return collect();
        }

        $fromValue = LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(isAll: true)
            ->where('lookup_type_id', $type->id)
            ->where('code', $fromValueCode)
            ->first();

        if (!$fromValue) {
            return collect();
        }

        return LookupTransition::query()
            ->with('toValue')
            ->where('lookup_type_id', $type->id)
            ->where('from_value_id', $fromValue->id)
            ->get()
            ->pluck('toValue');
    }

    /**
     * Get allowed transitions from a specific value
     *
     * @param int $lookupTypeId
     * @param int $fromValueId
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getTransitionsById(int $lookupTypeId, int $fromValueId): Collection {
        return LookupTransition::query()
            ->with('toValue')
            ->where('lookup_type_id', $lookupTypeId)
            ->where('from_value_id', $fromValueId)
            ->get()
            ->pluck('toValue');
    }

    /**
     * Check if a transition is allowed
     *
     * @param string $typeCode
     * @param string $fromValueCode
     * @param string $toValueCode
     *
     * @return bool
     */
    public static function isTransitionAllowed(string $typeCode, string $fromValueCode, string $toValueCode, bool $isValuesCodeId = false): bool {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return false;
        }

        if ($isValuesCodeId) {
            return LookupTransition::query()
                ->where('lookup_type_id', $type->id)
                ->where('from_value_id', $fromValueCode)
                ->where('to_value_id', $toValueCode)
                ->exists();
        }

        $fromValue = LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(isAll: true)
            ->where('lookup_type_id', $type->id)
            ->where('code', $fromValueCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        $toValue = LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(isAll: true)
            ->where('lookup_type_id', $type->id)
            ->where('code', $toValueCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$fromValue || !$toValue) {
            return false;
        }

        return LookupTransition::query()
            ->where('lookup_type_id', $type->id)
            ->where('from_value_id', $fromValue->id)
            ->where('to_value_id', $toValue->id)
            ->exists();
    }

    /**
     * Get lookup value by ID
     *
     * @param int $valueId
     * @return \App\Models\Common\Lookup\LookupValue|null
     */
    public static function getValueById(int $valueId): ?LookupValue {
        return LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(isAll: true)
            ->where('id', $valueId)
            ->where('state', STATE_ACTIVE)
            ->first();
    }

    /**
     * Get lookup value by code within a type
     *
     * @param string $typeCode
     * @param string $valueCode
     *
     * @return \App\Models\Common\Lookup\LookupValue|int|null
     */
    public static function getValueByCode(string $typeCode, string $valueCode, bool $needId = false): LookupValue|int|null {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return null;
        }

        $value = LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(isAll: true)
            ->where('lookup_type_id', $type->id)
            ->where('code', $valueCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if ($value && $needId) {
            return $value->id;
        }

        return $value;
    }

    /**
     * Resolve several lookup value ids under one type code in a SINGLE query.
     *
     * Resolving every requested code here costs ONE round-trip instead of one
     * per code, and the caller drops plain integer arrays into whereIn /
     * whereNotIn that work regardless of the outer connection.
     *
     * @param string $typeCode
     * @param array<int,string> $valueCodes
     *
     * @return array<string,int> map of value code => lookup value id
     */
    public static function valueIdsByCodes(string $typeCode, array $valueCodes): array {
        $lookupTypeQuery = LookupType::query()
            ->select('id')
            ->where('state', STATE_ACTIVE)
            ->where('code', $typeCode);

        return LookupValue::query()
            ->whereIn('lookup_type_id', $lookupTypeQuery)
            ->where('state', STATE_ACTIVE)
            ->whereIn('code', $valueCodes)
            ->pluck('id', 'code')
            ->toArray();
    }

    /**
     * Get lookup type by code
     *
     * @param string $typeCode
     * @return \App\Models\Common\Lookup\LookupType|null
     */
    public static function getTypeByCode(string $typeCode): ?LookupType {
        return LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();
    }

    /**
     * Check if a lookup type exists by code
     *
     * @param string $typeCode
     * @return bool
     */
    public static function typeExists(string $typeCode): bool {
        return LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->exists();
    }

    /**
     * Check if a lookup value exists by type code and value code
     *
     * @param string $typeCode
     * @param string $valueCode
     * @param bool $includePending
     *
     * @return bool
     */
    public static function valueExists(string $typeCode, string $valueCode, bool $includePending = false): bool {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return false;
        }

        return LookupValue::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->applyStatusBasedQuery(
                isAll: true,
                includePending: $includePending
            )
            ->where('lookup_type_id', $type->id)
            ->where('code', $valueCode)
            ->where('state', STATE_ACTIVE)
            ->exists();
    }

    /**
     * Check if a lookup value exists by type code and value ID
     *
     * @param string $typeCode
     * @param int $valueId
     * @param bool $includePending
     *
     * @return bool
     */
    public static function exists(string $typeCode, int $valueId, bool $includePending = false): bool {
        $type = LookupType::query()
            // ->applyRoleBasedQuery(permissionKey: PERMISSION_SEE_DYNAMIC_VALUE)
            ->where('code', $typeCode)
            ->where('state', STATE_ACTIVE)
            ->first();

        if (!$type) {
            return false;
        }

        return LookupValue::query()
            ->applyStatusBasedQuery(
                isAll: true,
                includePending: $includePending
            )
            ->where('id', $valueId)
            ->where('lookup_type_id', $type->id)
            ->where('state', STATE_ACTIVE)
            ->exists();
    }
}
