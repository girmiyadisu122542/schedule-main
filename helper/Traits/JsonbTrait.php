<?php

namespace Helper\Traits;

use Constants\AppConstant;
use Illuminate\Database\Eloquent\Builder;

trait JsonbTrait {
    /**
     * Scope for searching a value in a JSONB language-keyed column (all languages)
     *
     * @param Builder $query
     * @param string $column
     * @param string $search
     * @return Builder
     */
    public function scopeJsonbLangValueSearch(Builder $query, string $column, string $search, ?bool $or = false): Builder {
        $sql = "EXISTS (SELECT 1 FROM jsonb_each_text($column) AS t(k, v) WHERE LOWER(v) LIKE ?)";
        $searchTerm = '%' . strtolower($search) . '%';

        if ($or) {
            return $query->orWhereRaw(
                $sql,
                [$searchTerm]
            );
        }

        return $query->whereRaw(
            $sql,
            [$searchTerm]
        );
    }

    /**
     * Check if a JSONB language-keyed column has an exact value for a given language
     *
     * @param Builder $query
     * @param string $column
     * @param string $lang
     * @param string $value
     * @return Builder
     */
    public function scopeJsonbLangExactValue(Builder $query, string $column, string $lang, string $value): Builder {
        return $query->whereRaw(
            "LOWER($column->>?) = ?",
            [$lang, strtolower($value)]
        );
    }


    /**
     * Scope for searching a value in a JSONB language-keyed column (all languages)
     *
     * @param Builder $query
     * @param string $column
     * @param string $search
     * @return Builder
     */
    public function scopeOrJsonbLangValueSearch(Builder $query, string $column, string $search): Builder {
        return $query->jsonbLangValueSearch($column, $search, true);
    }

    /**
     * Check if a JSONB language-keyed value is unique in the table (optionally excluding a record by id)
     *
     * @param string $column
     * @param string $lang
     * @param string $value
     * @param \Closure|null $callback
     * @param null|string $targetColumn
     * @param int|string|null $ignoreId

     * @return bool
     */
    public static function checkJsonbLangValueUnique(
        string $lang,
        string $value,
        string $column,
        int|string|null $ignoreId,
        ?\Closure $callback = null,
        string $targetColumn = 'id',
    ): bool {
        $query = static::query()
            ->whereRaw(
                "LOWER($column->>?) = ?",
                [$lang, strtolower($value)]
            );

        if ($ignoreId) {
            $query->where($targetColumn, '!=', $ignoreId);
        }

        if ($callback != null) {
            $query = $callback($query);
        }
        return !$query->exists();
    }

    /**
     * Check if a JSONB  value is unique in the table (optionally excluding a record by id)
     *
     * @param string $value
     * @param string $column
     * @param \Closure|null $callback
     * @param int|null|string $ignoreId
     * @param int|null|string $targetColumn
     * @return bool
     */
    public static function checkJsonbValueUniqueStrict(
        string $column,
        string $value,
        int|string|null $ignoreId = null,
        ?\Closure $callback = null,
        string $targetColumn = 'id',
    ): bool {
        $query = static::query()
            ->where(function ($query) use ($column, $value) {
                foreach (AppConstant::DB_LANGUAGES as $lang => $langName) {
                    $query->orWhereRaw("LOWER($column->>?) = ?", [$lang, strtolower($value)]);
                }
            });

        if ($ignoreId) {
            $query->where($targetColumn, '!=', $ignoreId);
        }

        if ($callback != null) {
            $query = $callback($query);
        }

        return !$query->exists();
    }
}
