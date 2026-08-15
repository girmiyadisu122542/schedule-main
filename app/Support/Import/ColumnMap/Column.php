<?php

namespace App\Support\Import\ColumnMap;

use Closure;

/**
 * One spreadsheet column, declared once and consumed three ways: the export
 * writer reads {@see self::export()} for its cell, the template generator reads
 * {@see self::$example} for its worked row, and the importer reads
 * {@see self::$rules} plus the resolution hints to turn a cell back into a model
 * attribute.
 *
 * Nothing here is a header string typed twice — the human header is always
 * derived from {@see self::label()}, which resolves `$key` through the
 * `attributes` lang files.
 */
class Column {
    /** Plain text, written as-is. */
    public const TYPE_STRING = 'string';

    /** Whole number. */
    public const TYPE_INTEGER = 'integer';

    /** Fractional number — credit hours, weekly loads. */
    public const TYPE_DECIMAL = 'decimal';

    /** Yes/No cell, coerced through ImportConstant's spellings. */
    public const TYPE_BOOLEAN = 'boolean';

    /**
     * A jsonb translatable column. The sheet carries ONE plain string; the
     * importer wraps it into `{en: ...}` / merges it with `updateLangField`, and
     * the exporter emits the localized value. The raw `{en, am}` object never
     * crosses a spreadsheet — Final Schema.md conventions, CLAUDE §11.5.
     */
    public const TYPE_TRANSLATABLE = 'translatable';

    /** The machine header key, e.g. `college_code`. Canonical in error rows. */
    public string $key;

    /** The model attribute this column ultimately writes. */
    public string $attribute;

    /** One of the TYPE_* constants. */
    public string $type = self::TYPE_STRING;

    /** A blank cell is an error when true. */
    public bool $required = false;

    /** Laravel rules applied to the COERCED value in pass 1. */
    public array $rules = [];

    /**
     * Resolve this cell against another table, e.g.
     * `['model' => College::class, 'column' => 'code']`. Null for plain columns.
     *
     * @var array{model: class-string, column: string}|null
     */
    public ?array $relation = null;

    /** A `lookup_types.code` when this cell names a lookup value by its code. */
    public ?string $lookupType = null;

    /** The worked value a generated template shows. */
    public mixed $example = null;

    /** Renders a model into this column's cell. Defaults to the raw attribute. */
    public ?Closure $exportUsing = null;

    /**
     * Batched resolver for a cell whose target has no single stable column.
     *
     * `semesters` are identified by `(academic_year, term)` and `sections` by
     * `(program, year_level, label)` — neither has a `code` a plain
     * {@see self::resolvesTo()} could match on. Receives every distinct value in
     * the file at once and returns `[loweredValue => id]`, so it stays one query
     * per column rather than one per row.
     *
     * @var \Closure|null
     */
    public ?Closure $resolver = null;

    /**
     * Written on export, ignored on import.
     *
     * For columns worth reading in a download but not worth trusting in an
     * upload — an offering's `status`, most of all: a status column the importer
     * honoured would be a spreadsheet route to `registrar_approved` with no
     * approvals and no trail.
     */
    public bool $exportOnly = false;

    /**
     * @param string $key machine header key
     * @param string|null $attribute model attribute written; defaults to $key
     */
    public function __construct(string $key, ?string $attribute = null) {
        $this->key = $key;
        $this->attribute = $attribute ?? $key;
    }

    /**
     * Named constructor, so column lists read as a table.
     *
     * @param string $key machine header key
     * @param string|null $attribute model attribute written; defaults to $key
     *
     * @return self
     */
    public static function make(string $key, ?string $attribute = null): self {
        return new self($key, $attribute);
    }

    /**
     * Mark the column mandatory.
     *
     * @return self
     */
    public function required(): self {
        $this->required = true;

        return $this;
    }

    /**
     * Set the value type used for coercion.
     *
     * @param string $type one of the TYPE_* constants
     * @return self
     */
    public function type(string $type): self {
        $this->type = $type;

        return $this;
    }

    /**
     * Attach the validation rules for the coerced value.
     *
     * @param array $rules
     * @return self
     */
    public function rules(array $rules): self {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Resolve this cell against another table by a stable column.
     *
     * FKs are ALWAYS resolved this way — a sheet a registrar fills in carries
     * `CS`, never `7` (CLAUDE §10.7).
     *
     * @param class-string $model related model
     * @param string $column the stable column to match on — `code`, `email`
     *
     * @return self
     */
    public function resolvesTo(string $model, string $column = 'code'): self {
        $this->relation = ['model' => $model, 'column' => $column];

        return $this;
    }

    /**
     * Resolve this cell to a `lookup_values.id` by the value's stable code.
     *
     * @param string $lookupType a `lookup_types.code`
     * @return self
     */
    public function resolvesToLookup(string $lookupType): self {
        $this->lookupType = $lookupType;

        return $this;
    }

    /**
     * Resolve this cell with a batched closure — see {@see self::$resolver}.
     *
     * @param \Closure $resolver receives array<string, string> lowered => original
     * @return self
     */
    public function resolvesUsing(Closure $resolver): self {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Mark the column readable but not writable.
     *
     * @return self
     */
    public function exportOnly(): self {
        $this->exportOnly = true;

        return $this;
    }

    /**
     * The worked value a generated template carries.
     *
     * @param mixed $example
     * @return self
     */
    public function example(mixed $example): self {
        $this->example = $example;

        return $this;
    }

    /**
     * Override how a model renders into this cell.
     *
     * @param \Closure $callback receives the model, returns the cell value
     * @return self
     */
    public function exportUsing(Closure $callback): self {
        $this->exportUsing = $callback;

        return $this;
    }

    /**
     * The human header, resolved for a locale. Never a literal — a header typed
     * in two files is exactly the drift this design exists to prevent.
     *
     * @param string|null $locale defaults to the active application locale
     * @return string
     */
    public function label(?string $locale = null): string {
        $translated = __('attributes.' . $this->key, [], $locale);

        // `__()` echoes the key back when the entry is missing; a bare
        // `attributes.college_code` header would still be usable, but the
        // machine key alone reads better on a printed sheet.
        return is_string($translated) && $translated !== 'attributes.' . $this->key
            ? $translated
            : $this->key;
    }

    /**
     * Render a model into this column's cell value.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return mixed
     */
    public function export($model): mixed {
        if ($this->exportUsing !== null) {
            return ($this->exportUsing)($model);
        }

        if ($this->type === self::TYPE_TRANSLATABLE) {
            return $model->{$this->attribute . '__localized'};
        }

        return $model->{$this->attribute};
    }
}
