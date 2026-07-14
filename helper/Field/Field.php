<?php

namespace Helper\Field;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class Field {

    /**
     * The key used to
     * access relation values
     *
     * @var string RELATION_KEY
     */
    protected const RELATION_KEY = '.';

    /**
     * The field name which will be used when
     * returning the data
     *
     * @var string
     */
    protected $name;

    /**
     * The key | column that will be used to access
     * or set the data of the model
     * if not set the name will act as a key
     *
     * @var string|null
     */
    protected $key = null;

    /**
     * A callback function used when indexing datas
     *
     * @var \Closure|null
     */
    protected $resolveUsing = null;

    /**
     * The model where the field currently is defined
     *
     * @var mixed $model
     */
    protected $model;

    /**
     * Formatter
     *
     * @var \Closure $formatter
     */
    protected $formatter = null;

    /**
     * Default value
     *
     * @var mixed $default
     */
    protected $default = null;

    /**
     * The Disk used to
     * handle the file
     *
     * @var string|null $disk
     */
    protected $disk = null;

    /**
     * Allows the field to
     * return a secure and temporary
     * url for a file
     *
     * @var bool $secureUrl
     */
    protected $secureUrl = false;

    /**
     * initiates the fields calls
     *
     * @param string $name
     * @param string|null $key
     *
     * @return \Helper\Field\Field
     */
    public function __construct($name, $key = null) {
        $this->name = $name;

        if ($key !== null) {
            if ($key instanceof \Closure) {
                $this->resolveUsing = $key;
                $this->key = $this->name;
            } else {
                $this->key = $key;
            }
        } else {
            $this->key = $this->name;
            $this->name = str_replace('.', '_', $this->name);
        }

        return $this;
    }

    /**
     * For example if we have a field called zone_name
     * we usually use Field::make('zone_name') which
     * takes a little bit of space. instead we can
     * shorten the code to Field::zoneName() which
     * will return the same value as the above code
     * Thats what the below function does when called
     * staticaly
     *
     * @param string $methodName
     * @param array $arguments
     *
     * @return \Helper\Field\Field
     */
    public static function __callStatic($methodName, $arguments): Field {
        return static::make(Str::snake($methodName), ...$arguments);
    }

    /**
     * Returns a new Field instance
     *
     * @param string $name
     * @param \Closure|string|null $key
     *
     * @return \Helper\Field\Field
     */
    public static function make($name, $key = null): Field {
        return new static($name, $key);
    }

    /**
     * returns the name of this field
     *
     * @return string $name
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * returns the key of this field
     *
     * @return string $key
     */
    public function getKey(): string {
        return $this->key;
    }

    /**
     * Sets the model where the field
     * currently is defined
     *
     * @param mixed $model
     * @return mixed
     */
    public function setModel($model): mixed {
        $this->model = $model;
        return $this;
    }

    /**
     * Returns the model
     *
     * @return mixed
     */
    public function getModel(): mixed {
        return $this->model;
    }

    /**
     * returns the data based on the given name and key
     * from the selected model
     *
     * @param mixed $model
     * @return mixed
     */
    public function resolveData($model = null): mixed {
        if ($model !== null) {
            $this->setModel($model);
        }

        if ($this->resolveUsing !== null) {
            $resolve = $this->resolveUsing;
            return $resolve($this->getModel());
        }

        $keys = explode(static::RELATION_KEY, $this->getKey());
        $fieldData = $this->getModel();
        try {
            foreach ($keys as $newKey) {
                if ($fieldData->{ $newKey } !== null) {
                    $fieldData = $fieldData->{ $newKey };
                    continue;
                }

                return $this->default;
            }
        } catch (Throwable $e) {
            return $this->default;
        }

        return $this->formatData($fieldData);
    }

    /**
     * Returns a avalue from json column
     * based on the user selected langauge
     *
     * @param string $name
     * @param string|null|bool $key
     * @param bool|null $useFirst
     *
     * @return \Helper\Field\Field
     */
    public static function makeLocalized($name, $key = null, $useFirst = null): Field {
        if (is_bool($key) && $useFirst === null) {
            $useFirst = $key;
            $key = null;
        }

        $self = new static($name, $key);
        $self->resolveUsing = function ($data) use ($self, $useFirst) {
            $newField = new static($self->getName(), $self->getKey());
            $data = $newField->resolveData($self->getModel());
            $lang = getCurrentLanguage(request());

            if (isset($data[$lang])) {
                return $self->formatData($data[$lang]);
            }

            if ($useFirst && is_array($data) && Arr::isAssoc($data) && count(array_values($data)) > 0) {
                return $self->formatData(array_values($data)[0]);
            }

            return $self->default;
        };

        return $self;
    }

    /**
     * Adds support for image
     *
     * @param string $name
     * @param string|null $key
     * @param int $expiryTime
     *
     * @return \Helper\Field\Field
     */
    public static function makeFile($name, $key = null, $expiryTime = 60): Field {
        $self = new static($name, $key);
        $self->resolveUsing = function ($data) use ($self, $expiryTime) {
            $newField = new static($self->getName(), $self->getKey());
            $data = $newField->resolveData($self->getModel());

            $data ??= $self->default;
            $disk = $self->disk ?? DEFAULT_STORAGE;

            if ($data === null) {
                return $self->default;
            }

            if ($self->secureUrl) {
                return URL::temporarySignedRoute(
                    SECURE_FILE_ROUTE,
                    now()->addMinutes($expiryTime),
                    ['disk' => $disk, 'path' => ltrim($data, '/')]
                );
            }

            return Storage::disk($disk)->url($data);
        };

        return $self;
    }

    /**
     * Resolve from type classes
     *
     * @param string $name
     * @param string|null $key
     * @param string|null $typeClass
     *
     * @return \Helper\Field\Field
     */
    public static function makeType($name, $key = null, $typeClass = null, $typeFunction = 'typeNames'): Field {
        if ($typeClass === null || class_exists($key)) {
            $typeClass = $key;
            $key = null;
        }

        $self = new static($name, $key);
        $self->resolveUsing = function ($data) use ($self, $typeClass, $typeFunction) {
            $newField = new static($self->getName(), $self->getKey());
            $data = $newField->resolveData($self->getModel());

            if ($data === null) {
                return $self->default;
            }

            $typeResponse = $typeClass::{ $typeFunction }($data);
            return $typeResponse ?? $this->default;
        };

        return $self;
    }

    /**
     * builds a relationship name based
     * on the return name provided
     *
     * @param string $name
     * @return string
     */
    protected static function buildRelationshipFromName($name): string {
        return Str::camel($name);
    }

    /**
     * Returns a resource from a relationship
     *
     * @param string $name
     * @param string|null $key
     * @param string|array|null $fields
     *
     * @return \Helper\Field\Field
     */
    public static function makeResource($name, $key = null, $fields = null): Field {
        if ($key == null) {
            $key = static::buildRelationshipFromName($name);
        }

        $self = new static($name, $key);
        $self->resolveUsing = function ($data) use ($self, $fields) {
            $newField = new static($self->getName(), $self->getKey());
            $data = $newField->resolveData($self->getModel());

            if ($data === null) {
                return $self->default;
            }

            $returnData = $self->default;
            try {
                $returnData = $data?->resource($fields);
            } catch (Throwable $e) {
                return $self->default;
            }

            return $returnData;
        };

        return $self;
    }

    /**
     * Returns a collection from a relationship
     *
     * @param string $name
     * @param string|null $key
     * @param string|array|null $fields
     *
     * @return \Helper\Field\Field
     */
    public static function makeCollection($name, $key = null, $fields = null): Field {
        if ($key == null) {
            $key = static::buildRelationshipFromName($name);
        }

        $self = new static($name, $key);
        $self->resolveUsing = function ($data) use ($self, $fields) {
            $newField = new static($self->getName(), $self->getKey());
            $data = $newField->resolveData($self->getModel());

            if ($data === null) {
                return $self->default;
            }

            $returnData = $self->default;
            try {
                $returnData = $data?->collection($fields);
            } catch (Throwable $e) {
                return $self->default;
            }

            return $returnData;
        };

        return $self;
    }

    /**
     * Formates the returned data
     *
     * @param \Closure $callback
     * @return \Helper\Field\Field
     */
    public function format($callback): Field {
        $this->formatter = $callback;
        return $this;
    }

    /**
     * Formates the returned data based on the formatter
     *
     * @param mixed $value
     * @return mixed
     */
    public function formatData($value): mixed {
        if (!$this->formatter) {
            return $value;
        }

        $formatter = $this->formatter;
        return $formatter($value, $this->getModel());
    }

    /**
     * Format the value as integer
     *
     * @return \Helper\Field\Field
     */
    public function asInt(): Field {
        return $this->format(fn ($value) => intval($value));
    }

    /**
     * Format the value as double
     *
     * @return \Helper\Field\Field
     */
    public function asDouble(): Field {
        return $this->format(fn ($value) => doubleval($value));
    }

    /**
     * Format the value as boolean
     *
     * @return \Helper\Field\Field
     */
    public function asBool(): Field {
        return $this->format(fn ($value) => boolval($value));
    }

    /**
     * Sets the default value for the field
     *
     * @param mixed $default
     * @return \Helper\Field\Field
     */
    public function default($default): Field {
        $this->default = $default;
        return $this;
    }

    /**
     * Sets the disk for the image
     *
     * @param string $disk
     * @return \Helper\Field\Field
     */
    public function disk($disk): Field {
        $this->disk = $disk;
        return $this;
    }

    /**
     * Sets if the file url should
     * be a secure url
     *
     * @return \Helper\Field\Field
     */
    public function secureUrl(): Field {
        $this->secureUrl = true;
        return $this;
    }

    /**
     * Register macros
     * for \Illuminate\Database\Eloquent\Collection
     *
     * @return void
     */
    public static function registerMacros(): void {
        Collection::macro('collection', function ($fields = null) {
            return $this->map(function ($model) use ($fields) {
                return $model->resource($fields);
            });
        });
    }
}
