<?php

namespace Helper\Model;

use Helper\Permission\RoleBasedQuery;
use Illuminate\Database\Eloquent\Builder;

class ScopedModel extends BaseModel {
    use RoleBasedQuery;

    /**
     * The name of the global scope
     * roleBasedQuery
     *
     * @var string
     */
    public const ROLE_BASED_QUERY = 'roleBasedQuery';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {
        $forcedConstantName = get_class(new static()) . "::FORCE_ROLE_BASED_QUERY";
        $isRoleBasedQueryForced = defined($forcedConstantName) && constant($forcedConstantName) == true;
        if ($isRoleBasedQueryForced) {
            static::addGlobalScope(static::ROLE_BASED_QUERY, function (Builder $query) {
                $query->applyRoleBasedQuery();
            });
        }

        parent::booted();
    }

    /**
     * Applies the withoutGlobalScope directive
     * to the whole query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutRoleBasedQuery(Builder $query): Builder {
        return $query->withoutGlobalScope(static::ROLE_BASED_QUERY);
    }
}
