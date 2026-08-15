<?php

namespace Helper\Traits;

use Constants\AppConstant;
use Illuminate\Database\Eloquent\Builder;

trait JsonbTrait {
    /**
     * SQL that reads one language key out of a JSON column as plain text.
     *
     * MariaDB/MySQL have no `->>` operator (MySQL 5.7+ has one, MariaDB has
     * none at all), so the portable spelling is JSON_UNQUOTE(JSON_EXTRACT()).
     * The path is bound as a parameter — `$.en`, not the bare `en` the
     * PostgreSQL operator took.
     *
     * @param string $column
     * @return string
     */
    protected static function jsonLangText(string $column): string {
        return "JSON_UNQUOTE(JSON_EXTRACT($column, ?))";
    }

    /**
     * The bindable JSON path for a language key.
     *
     * @param string $lang
     * @return string
     */
    protected static function jsonLangPath(string $lang): string {
        return '$."' . str_replace('"', '', $lang) . '"';
    }

    /**
     * Scope for searching a value in a JSON language-keyed column (all languages)
     *
     * PostgreSQL walked every key with `jsonb_each_text`. MariaDB has no such
     * set-returning function, and the columns are language maps with a known
     * key set anyway — so the search is an OR across AppConstant::DB_LANGUAGES.
     *
     * @param Builder $query
     * @param string $column
     * @param string $search
     * @return Builder
     */
    public function scopeJsonbLangValueSearch(Builder $query, string $column, string $search, ?bool $or = false): Builder {
        $searchTerm = '%' . strtolower($search) . '%';
        $text = static::jsonLangText($column);

        $group = function ($query) use ($text, $column, $searchTerm) {
            foreach (array_keys(AppConstant::DB_LANGUAGES) as $lang) {
                $query->orWhereRaw(
                    "LOWER($text) LIKE ?",
                    [static::jsonLangPath($lang), $searchTerm]
                );
            }
        };

        return $or ? $query->orWhere($group) : $query->where($group);
    }

    /**
     * Check if a JSON language-keyed column has an exact value for a given language
     *
     * @param Builder $query
     * @param string $column
     * @param string $lang
     * @param string $value
     * @return Builder
     */
    public function scopeJsonbLangExactValue(Builder $query, string $column, string $lang, string $value): Builder {
        return $query->whereRaw(
            'LOWER(' . static::jsonLangText($column) . ') = ?',
            [static::jsonLangPath($lang), strtolower($value)]
        );
    }


    /**
     * Scope for searching a value in a JSON language-keyed column (all languages)
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
     * Check if a JSON language-keyed value is unique in the table (optionally excluding a record by id)
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
                'LOWER(' . static::jsonLangText($column) . ') = ?',
                [static::jsonLangPath($lang), strtolower($value)]
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
     * Check if a JSON value is unique in the table (optionally excluding a record by id)
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
        $text = static::jsonLangText($column);

        $query = static::query()
            ->where(function ($query) use ($text, $value) {
                foreach (array_keys(AppConstant::DB_LANGUAGES) as $lang) {
                    $query->orWhereRaw(
                        "LOWER($text) = ?",
                        [static::jsonLangPath($lang), strtolower($value)]
                    );
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
