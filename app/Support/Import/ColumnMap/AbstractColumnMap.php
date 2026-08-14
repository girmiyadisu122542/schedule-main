<?php

namespace App\Support\Import\ColumnMap;

use App\Constants\ImportConstant;
use Translation\FrontLang;

/**
 * The single definition of what one entity's spreadsheet looks like.
 *
 * Three consumers derive from this and nothing else:
 *   1. `MasterDataExportService` — header row and cell order,
 *   2. the `import-template` endpoint — headers plus a worked example row,
 *   3. `MasterDataImportService` — header validation and per-row rules.
 *
 * A template that drifts from what the importer accepts is the specific failure
 * this class exists to prevent. If a header string is being typed in a second
 * file, it belongs here instead.
 */
abstract class AbstractColumnMap {
    /**
     * The entity slug — drives the Message bucket, the export filename and the
     * `export:<slug>` / `import:<slug>` permission keys.
     *
     * @return string
     */
    abstract public function entityKey(): string;

    /**
     * The Eloquent model this map reads and writes.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    abstract public function modelClass(): string;

    /**
     * The ordered column list. Cell order on every sheet follows it.
     *
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    abstract public function columns(): array;

    /**
     * The column keys whose combined values identify an existing record — what
     * `upsert` matches on and what `create_only` rejects a duplicate of.
     *
     * Most entities key on `['code']`; instructors key on `['employee_no']`;
     * sections have no unique code at all and key on the composite unique
     * `(program, academic year, year level, label)` the database already
     * enforces (Final Schema.md §8).
     *
     * @return array<int, string>
     */
    abstract public function naturalKey(): array;

    /**
     * Relations the export query eager-loads so FK-code columns do not issue a
     * query per row.
     *
     * @return array<int, string>
     */
    public function exportWith(): array {
        return [];
    }

    /**
     * Does a created row get `user_id` stamped with the acting user?
     *
     * True for every master-data table except `instructors`, whose `user_id` is
     * the person's portal account rather than a creator column (Final Schema.md
     * §11) — stamping it there would silently claim the row belongs to whoever
     * ran the import.
     *
     * @return bool
     */
    public function stampsCreator(): bool {
        return true;
    }

    /**
     * Business rules a per-column rule cannot express, checked in pass 1 once
     * the row's references have resolved.
     *
     * @param array<string, mixed> $values the coerced row, keyed by machine header key
     *
     * @return array<int, array{column: string|null, key: string}> error keys to report
     */
    public function validateRow(array $values): array {
        return [];
    }

    /**
     * Natural-key parts the schema allows to be null.
     *
     * A null there is not a MISSING key, it IS the key — `(semester, course, ∅)`
     * is precisely the identity a partial unique `WHERE section_id IS NULL`
     * enforces. Without this the signature aborts, the row looks new, and the
     * insert trips a raw constraint violation instead of a per-row message.
     *
     * @return array<int, string>
     */
    public function nullableKeyParts(): array {
        return [];
    }

    /**
     * Attributes stamped on CREATE only, that no sheet column may supply.
     *
     * @return array<string, mixed>
     */
    public function defaultAttributes(): array {
        return [];
    }

    /**
     * The column that records who created the row, or null to stamp nobody.
     *
     * Not every table spells it `user_id`: `course_offerings` uses
     * `created_by_id`, and `instructors.user_id` is the person themselves.
     *
     * @return string|null
     */
    public function creatorAttribute(): ?string {
        return $this->stampsCreator() ? 'user_id' : null;
    }

    /**
     * Business rules that need the RESOLVED attributes and the record the row
     * would update — scope checks, and "is this row still editable".
     *
     * @param array<string, mixed> $values the coerced row
     * @param array<string, mixed> $attributes the resolved attributes
     * @param \Illuminate\Database\Eloquent\Model|null $record the row being updated, if any
     *
     * @return array<int, array{column: string|null, key: string}>
     */
    public function validateResolvedRow(array $values, array $attributes, $record): array {
        return [];
    }

    /**
     * Runs inside pass 2's transaction, after the row is written — for child
     * collections a column cannot express on its own.
     *
     * @param \Illuminate\Database\Eloquent\Model $record
     * @param array<string, mixed> $values the coerced row
     *
     * @return void
     */
    public function afterWrite($record, array $values): void {
    }

    /**
     * The localized header row, in column order.
     *
     * @param string|null $locale defaults to the active application locale
     * @return array<int, string>
     */
    public function headers(?string $locale = null): array {
        return array_map(fn (Column $column) => $column->label($locale), $this->columns());
    }

    /**
     * The machine header keys, in column order.
     *
     * @return array<int, string>
     */
    public function headerKeys(): array {
        return array_map(fn (Column $column) => $column->key, $this->columns());
    }

    /**
     * Zero-based indexes of the columns a spreadsheet must store as TEXT.
     *
     * Anything a reader would otherwise coerce to a number and damage: phone
     * numbers ("+2519…" loses its plus), zero-padded codes ("007" becomes 7),
     * and every code that resolves a foreign key.
     *
     * @return array<int, int>
     */
    public function textColumnIndexes(): array {
        $indexes = [];

        foreach (array_values($this->columns()) as $index => $column) {
            $isTextual = $column->type === Column::TYPE_STRING
                || $column->type === Column::TYPE_TRANSLATABLE;

            if ($isTextual) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * The keys a blank cell is an error for.
     *
     * @return array<int, string>
     */
    public function requiredKeys(): array {
        $required = array_filter($this->columns(), fn (Column $column) => $column->required && !$column->exportOnly);

        return array_values(array_map(fn (Column $column) => $column->key, $required));
    }

    /**
     * Per-column validation rules, keyed by machine header key.
     *
     * @return array<string, array>
     */
    public function rules(): array {
        $rules = [];

        foreach ($this->columns() as $column) {
            // An export-only column is still a legal header, so a downloaded
            // file re-imports — its cell is simply not validated or written.
            if ($column->exportOnly) {
                continue;
            }

            $rules[$column->key] = array_merge(
                [$column->required ? 'required' : 'nullable'],
                $column->rules
            );
        }

        return $rules;
    }

    /**
     * Look one column up by its machine key.
     *
     * @param string $key
     * @return \App\Support\Import\ColumnMap\Column|null
     */
    public function column(string $key): ?Column {
        foreach ($this->columns() as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    /**
     * The worked example rows a generated template carries, in column order.
     *
     * @return array<int, array<int, mixed>>
     */
    public function exampleRows(): array {
        $row = array_map(fn (Column $column) => $column->example, $this->columns());

        return array_fill(0, ImportConstant::TEMPLATE_EXAMPLE_ROWS, $row);
    }

    /**
     * Every spelling of a header this map accepts on import, mapped back to the
     * machine key.
     *
     * Both the machine key AND the localized label in every supported language
     * are accepted, which is what lets a file exported in Amharic — Amharic
     * headers and all — be re-imported without a translation step.
     *
     * @return array<string, string> normalized header => machine key
     */
    public function headerAliases(): array {
        $aliases = [];

        foreach ($this->columns() as $column) {
            $aliases[static::normalizeHeader($column->key)] = $column->key;

            foreach (FrontLang::getAvailableLangKeys() as $locale) {
                $aliases[static::normalizeHeader($column->label($locale))] = $column->key;
            }
        }

        return $aliases;
    }

    /**
     * Fold a header cell to its comparison form: trimmed, lower-cased, and with
     * runs of whitespace, hyphens and underscores collapsed to one underscore.
     * "College Code", "college-code" and "COLLEGE_CODE" all match.
     *
     * @param string $header
     * @return string
     */
    public static function normalizeHeader(string $header): string {
        $folded = preg_replace('/[\s\-_]+/u', '_', trim($header));

        return mb_strtolower(trim((string) $folded, '_'));
    }
}
