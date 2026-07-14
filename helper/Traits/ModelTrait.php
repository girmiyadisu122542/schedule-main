<?php

namespace Helper\Traits;

use App\Rules\UniqueJsonbLangValue;
use Constants\AppConstant;
use Exception;
use Helper\Field\Field;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ModelTrait {

    /**
     * This varaible is used to force the use
     * of the laravel serialization instaed
     * of the Fields
     *
     * @var bool $doNotUseFields
     */
    protected $doNotUseFields = false;

    /**
     * Returns the current table name concatinated with the
     * connection name associated with the table
     *
     * @return string
     */
    public function getTable(): string {
        // $database = $this->getDatabaseName();
        // if($database == '' || str_contains($this->getOnlyTableName(), '.')) return $this->getOnlyTableName();

        // return  $database . '.' . $this->getOnlyTableName();

        return $this->getOnlyTableName();
    }

    /**
     * this class can be called staticaly and
     * return the value returned from the
     * getTable method
     *
     * @return string
     */
    public static function getTableName(): string {
        return (new static())->getTable();
    }

    /**
     * Returns the current connection
     * database name
     *
     * @return string
     */
    public function getDatabaseName(): string {
        if ($this->connection == null) {
            return '';
        }

        return DB::connection($this->connection)->getDatabaseName();
    }

    /**
     * Returns the table name for the current model
     * this is the same as the laravel getTable method
     *
     * @return string
     */
    public function getOnlyTableName(): string {
        return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Returns a laravel validation rule exists based on
     * the column passed
     *
     * @param string $column
     * @return string
     */
    public static function exists($column = 'id'): string {
        return 'exists:' . static::class . ',' . $column;
    }

    /**
     * Returns a laravel validation rule unique based on
     * the column passed
     *
     * @param string $column
     * @return string
     */
    public static function unique($column = 'id', $ignoreId = null): string {
        $model = new static();

        $connection = $model->getConnectionName();
        $table = $model->getTable();

        $prefix = $connection ? $connection . '.' : '';

        $rule = 'unique:' . $prefix . $table . ',' . $column;

        if ($ignoreId) {
            $rule .= ',' . $ignoreId;
        }

        return $rule;
    }


    /**
     * Returns a custom validation for unique
     * jsonb attribute for the current language
     * or user provided language
     *
     * @param string $column
     * @param ?string $message
     * @param ?string $language
     * @param mixed $ignoreId
     * @param string $targetColumn
     * @param ?\Closure $callback
     *
     * @return \Illuminate\Contracts\Validation\Rule
     */
    public static function uniqueJsonBRule(string $column, ?string $message, ?string $language = null, mixed $ignoreId = null, string $targetColumn = 'id', ?\Closure $callback = null): Rule {
        if ($language == null) {
            $language = getCurrentLanguage(request());
        }
        return new UniqueJsonbLangValue(model: static::class, column: $column, lang: $language, message: $message, ignoreId: $ignoreId, targetColumn: $targetColumn, strict: false, callback: $callback);
    }

    /**
     * Returns a custom validation for unique
     * jsonb attribute from every language
     *
     * @param string $column
     * @param ?string $message
     * @param ?string $language
     * @param mixed $ignoreId
     * @param string $targetColumn
     * @param ?\Closure $callback
     *
     * @return \Illuminate\Contracts\Validation\Rule
     */
    public static function uniqueJsonBRuleStrict(string $column, ?string $message, ?string $language = null, mixed $ignoreId = null, string $targetColumn = 'id', ?\Closure $callback = null): Rule {
        if ($language == null) {
            $language = getCurrentLanguage(request());
        }
        return new UniqueJsonbLangValue(model: static::class, column: $column, lang: $language, message: $message, ignoreId: $ignoreId, strict: true, targetColumn: $targetColumn, callback: $callback);
    }

    /**
     * Extracts the pagination data
     * from the collection
     *
     * @param Illuminate\Pagination\LengthAwarePaginator|array $collection
     * @return array<string, mixed>
     */
    public static function extractPagination(LengthAwarePaginator|array $collection): array {
        if ($collection instanceof LengthAwarePaginator) {
            return [
                'current_page' => $collection->currentPage(),
                'first_page_url' => $collection->url(1),
                'from' => $collection->firstItem(),
                'last_page' => $collection->lastPage(),
                'last_page_url' => $collection->url($collection->lastPage()),
                'links' => $collection->linkCollection()->toArray(),
                'next_page_url' => $collection->nextPageUrl(),
                'path' => $collection->path(),
                'per_page' => $collection->perPage(),
                'prev_page_url' => $collection->previousPageUrl(),
                'to' => $collection->lastItem(),
                'total' => $collection->total(),
            ];
        }

        unset($collection['data']);
        return $collection;
    }

    protected function belongsToOnConnection(string $class, string $connection, $foreignKey = null, $ownerKey = null, $relation = null) {
        $instance = new $class();
        $instance->setConnection($connection);

        return $this->belongsTo(get_class($instance), $foreignKey, $ownerKey, $relation);
    }

    /**
     * Filter records by relationship in a different database using Eloquent models.
     * Handles cross-database relationships by using subqueries instead of JOINs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $relatedModelClass
     * @param string $localKey
     * @param string $foreignKey
     * @param bool $includeNullColumn
     * @param \Closure|null $callback
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereHasCrossDatabase(
        $query,
        string $relatedModelClass,
        string $localKey,
        string $foreignKey = 'id',
        bool $includeNullColumn = false,
        ?\Closure $callback = null,
    ) {
        $relatedQuery = $relatedModelClass::query()
            ->when($callback, fn ($query) => $query->where($callback))
            ->pluck($foreignKey)
            ->toArray();

        return $query
            ->where(function ($query) use ($localKey, $relatedQuery, $includeNullColumn) {
                $query
                    ->whereIn($localKey, $relatedQuery)
                    ->when($includeNullColumn, fn ($query) => $query->orWhereNull($localKey));
            });
    }

    /**
     * Added functionality to the magic __get
     * to be able to access localized values
     * from the model
     *
     * @param string $variable
     * @return mixed
     */
    public function __get($variable): mixed {
        if (str_ends_with($variable, LOCALIZED_SUFFIX)) {
            return $this->localizedValueGetter($variable);
        }

        if (get_parent_class() == false) {
            $className = static::class;
            throw new Exception("Undefined variable: $className::$variable");
        }

        return parent::__get($variable);
    }

    /**
     * Returns the localized
     * values from a model variable
     *
     * @param string $variable
     * @return ?string
     */
    public function localizedValueGetter($variable): ?string {
        $suffix = str_ends_with($variable, LOCALIZED_SUFFIX_WITH_DEFAULT)
            ? LOCALIZED_SUFFIX_WITH_DEFAULT
            : LOCALIZED_SUFFIX;

        $variableLen = strlen($variable);
        $localizedLen = strlen($suffix);

        $prefix = substr($variable, 0, $variableLen - $localizedLen);

        $data = $this->{$prefix};
        if ($data == null || !is_array($data) || count($data) == 0) {
            return null;
        }

        $default = str_ends_with($variable, LOCALIZED_SUFFIX_WITH_DEFAULT)
            ? array_values($data)[0]
            : null;

        $language = getCurrentLanguage(request());
        return $data[$language] ?? $default;
    }

    public function getLocalizedKeyValue(array $data, bool $withDefault = false): ?string {
        if (!is_array($data) || empty($data)) {
            return null;
        }

        $language = getCurrentLanguage(request());
        return $withDefault
            ? ($data[$language] ?? array_values($data)[0] ?? null)
            : ($data[$language] ?? null);
    }

    /**
     * Generate a unique slug for the model using the trait's class.
     *
     * @param string $name
     * @param string $field
     * @return string
     */
    public static function generateSlug(string $name = "", string $field = 'slug'): string {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = (string) Str::uuid();
        }

        $count = 0;
        $base = $slug;
        $hasSoftDelete = method_exists(static::class, 'withTrashed');
        while (static::when($hasSoftDelete, fn ($query) => $query->withTrashed())->withoutGlobalScopes()->where($field, $slug)->exists()) {
            $count++;
            $slug = $base . '-' . $count;
        }

        return $slug;
    }

    /**
     * Fields that will be use when
     * Returning data to the browser
     *
     * @return array
     */
    public function indexFields(): array {
        return [];
    }

    /**
     * Forces the model to skip
     * the index Fields
     *
     * @param bool|\Closure
     * @return static
     */
    public function doNotUseFields($condition = true): mixed {
        if ($condition instanceof \Closure) {
            $condition = $condition();
        }

        $this->doNotUseFields = $condition;
        return $this;
    }

    /**
     * Returns resources from each field
     *
     * @param array|string|bool|null|\Helper\Field\Field $fields
     * @return mixed
     */
    public function resource($fields = null): mixed {
        if (is_bool($fields)) {
            $this->doNotUseFields(!$fields);
            return $this->toArray();
        }

        if ($fields == null) {
            $fields = $this->indexFields();
        } elseif (is_string($fields)) {
            $fields = $this->{$fields}();
        }

        if ($fields instanceof Field) {
            return $fields->resolveData($this);
        }

        $resource = [];
        foreach ($fields as $field) {
            $resource[$field->getName()] = $field->resolveData($this);
        }

        return $resource;
    }

    /**
     * Returns array of model from the given Illuminate\Database\Eloquent\Collection
     *
     * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Pagination\LengthAwarePaginator $collection
     * @param array|string|bool|null|\Helper\Field\Field $fields
     *
     * @return array
     */
    public static function collection($collection, $fields = null): array {
        $resources = [];
        foreach ($collection as $model) {
            array_push($resources, $model->resource($fields));
        }

        return $resources;
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array {
        if (count($this->indexFields()) == 0 || $this->doNotUseFields) {
            return $this->withoutRecursion(
                fn () => array_merge($this->attributesToArray(), $this->relationsToArray()),
                fn () => $this->attributesToArray(),
            );
        }

        return $this->resource();
    }

    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::makeLocalized('name'),
            Field::makeLocalized('display_name', 'name', useFirst: true),
        ];
    }

    public function dropdownFields(): array {
        return [
            Field::id(),
            Field::makeLocalized('name', 'name', true),
        ];
    }

    /**
     * Boot the ModelTrait to automatically generate UUIDs if the model has a uuid column
     *
     * @return void
     */
    protected static function bootModelTrait(): void {
        static::creating(function ($model) {
            $column = $model->uuidColumn ?? 'uuid';
            if ($model->hasColumn($column) && empty($model->$column)) {
                $model->$column = (string) Str::uuid();
            }
        });
    }

    /**
     * Helper function to check if the model has a column in its table
     *
     * @param string $column
     */
    public function hasColumn(string $column): bool {
        static $columnsCache = [];

        $connection = $this->getConnectionName();
        $table = $this->getTable();
        $cacheKey = ($connection ?? AppConstant::SCHEDULE_DATABASE_CONNECTION) . ':' . $table . ':' . $column;

        // Never cache a negative result: during migrate:fresh --seed (one process)
        // a column checked before its table exists would otherwise poison the cache
        // with false for the whole run, skipping uuid/user_id/state auto-fill at seed
        // time. Cache only a confirmed true; re-query while the answer is still false.
        if (!array_key_exists($cacheKey, $columnsCache) || $columnsCache[$cacheKey] === false) {
            $columnsCache[$cacheKey] = Schema::connection($connection)->hasColumn($table, $column);
        }

        return $columnsCache[$cacheKey];
    }
}
