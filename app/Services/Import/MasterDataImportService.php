<?php

namespace App\Services\Import;

use App\Constants\ImportConstant;
use App\Services\Lookup\LookupService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\Column;
use Constants\AppConstant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Translation\Message;

class MasterDataImportService {
    /**
     * Memoized accepted spellings of true/false — see {@see self::booleanSpellings()}.
     *
     * @var array{0: array<int, string>, 1: array<int, string>}|null
     */
    private ?array $booleanSpellings = null;

    /**
     * Import one entity's spreadsheet.
     *
     * TWO PASSES, and the distinction is the whole point:
     *
     *   Pass 1 validates every row and resolves every reference. NOTHING is
     *   written. Its report names the exact row and column of each failure.
     *
     *   Pass 2 runs only when pass 1 is clean (or the caller passed
     *   `skip_invalid`), and writes every valid row inside ONE transaction.
     *
     * Half-imported master data is worse than a rejected file: a partly-loaded
     * department list produces orphan programmes nobody notices until scheduling
     * fails months later. So the default is all-or-nothing.
     *
     * @param \Illuminate\Http\UploadedFile $file the uploaded spreadsheet
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map the entity's column map
     * @param array $options validated request payload — mode, dry_run, skip_invalid
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<int, array{row: int, column: string|null, message: string}>}|string
     */
    public function import($file, AbstractColumnMap $map, array $options = []) {
        $mode = $options['mode'] ?? ImportConstant::MODE_CREATE_ONLY;
        $isDryRun = (bool) ($options['dry_run'] ?? false);
        $skipInvalid = (bool) ($options['skip_invalid'] ?? false);

        $read = app(SpreadsheetReaderService::class)->read($file, $map);
        if ($read->failed()) {
            return $read->errorKey;
        }

        // ---- PASS 1 — validate and resolve. No writes. ----
        $plan = $this->buildPlan($read->rows, $read->rowNumbers, $map, $mode);

        if ($isDryRun) {
            return [
                'created' => $plan['createCount'],
                'updated' => $plan['updateCount'],
                'skipped' => count($plan['errors']),
                'errors' => $plan['errors'],
            ];
        }

        if ($plan['errors'] && !$skipInvalid) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => count($plan['rows']) + count($plan['errors']),
                'errors' => $plan['errors'],
            ];
        }

        // ---- PASS 2 — write, all of it or none of it. ----
        return $this->write($plan, $map);
    }

    /**
     * Pass 1: coerce, validate, resolve references and decide create-vs-update
     * for every row, without touching the database.
     *
     * @param array<int, array<string, mixed>> $rows keyed rows from the reader
     * @param array<int, int> $rowNumbers spreadsheet row number per row
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     * @param string $mode create_only or upsert
     *
     * @return array{rows: array<int, array>, errors: array<int, array>, createCount: int, updateCount: int}
     */
    private function buildPlan(array $rows, array $rowNumbers, AbstractColumnMap $map, string $mode): array {
        $coerced = [];
        foreach ($rows as $index => $row) {
            $coerced[$index] = $this->coerceRow($row, $map);
        }

        $references = $this->resolveReferences($coerced, $map);
        $existing = $this->findExistingRecords($coerced, $references, $map);

        $planned = [];
        $errors = [];
        $seenSignatures = [];
        $createCount = 0;
        $updateCount = 0;

        foreach ($coerced as $index => $values) {
            $rowNumber = $rowNumbers[$index];
            $rowErrors = $this->validateRow($values, $map, $rowNumber);

            [$attributes, $referenceErrors] = $this->resolveRowAttributes($values, $references, $map, $rowNumber);
            $rowErrors = array_merge($rowErrors, $referenceErrors);

            $signature = $this->signature($values, $attributes, $map);

            // A file that names the same record twice cannot be applied in one
            // pass — the second write would silently undo the first.
            if ($signature !== null && isset($seenSignatures[$signature])) {
                $rowErrors[] = $this->error($rowNumber, $map->naturalKey()[0], 'import_duplicate_in_file', [
                    'value' => $signature,
                    'row' => (string) $seenSignatures[$signature],
                ]);
            }

            $record = $signature !== null ? ($existing[$signature] ?? null) : null;

            if ($record && $mode === ImportConstant::MODE_CREATE_ONLY) {
                $rowErrors[] = $this->error($rowNumber, $map->naturalKey()[0], 'import_record_already_exists', [
                    'value' => $signature,
                ]);
            }

            foreach ($map->validateResolvedRow($values, $attributes, $record) as $resolvedError) {
                $rowErrors[] = $this->error($rowNumber, $resolvedError['column'], $resolvedError['key']);
            }

            if ($rowErrors) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            if ($signature !== null) {
                $seenSignatures[$signature] = $rowNumber;
            }

            $planned[] = [
                'attributes' => $attributes,
                'record' => $record,
                'values' => $values,
                'rowNumber' => $rowNumber,
            ];

            $record ? $updateCount++ : $createCount++;
        }

        return [
            'rows' => $planned,
            'errors' => $errors,
            'createCount' => $createCount,
            'updateCount' => $updateCount,
        ];
    }

    /**
     * Pass 2: write every planned row inside one transaction.
     *
     * @param array{rows: array<int, array>, errors: array<int, array>} $plan the pass-1 plan
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array{created: int, updated: int, skipped: int, errors: array}|string
     */
    private function write(array $plan, AbstractColumnMap $map) {
        $modelClass = $map->modelClass();
        $created = 0;
        $updated = 0;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($plan['rows'] as $planned) {
                $record = $planned['record'];
                $attributes = $planned['attributes'];

                if ($record) {
                    $attributes = $this->mergeTranslatables($attributes, $record, $map);
                    $record->fill($attributes);
                    $record->save();
                    $map->afterWrite($record, $planned['values'] ?? []);
                    $updated++;
                    continue;
                }

                $attributes['uuid'] = (string) Str::uuid();

                // Stamped on CREATE only, and never from a sheet column — this
                // is where an imported row's status is pinned to `draft`.
                $attributes = array_merge($attributes, $map->defaultAttributes());

                $creatorAttribute = $map->creatorAttribute();
                if ($creatorAttribute) {
                    $attributes[$creatorAttribute] = Auth::id();
                }

                $fresh = $modelClass::create($attributes);
                $map->afterWrite($fresh, $planned['values'] ?? []);
                $created++;
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return 'import_failed';
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($plan['errors']),
            'errors' => $plan['errors'],
        ];
    }

    /**
     * Coerce every cell to the type its column declares.
     *
     * @param array<string, mixed> $row the raw keyed row
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array<string, mixed>
     */
    private function coerceRow(array $row, AbstractColumnMap $map): array {
        $values = [];

        foreach ($map->columns() as $column) {
            $values[$column->key] = $this->coerce($row[$column->key] ?? null, $column);
        }

        return $values;
    }

    /**
     * Coerce one cell.
     *
     * @param mixed $value the raw cell
     * @param \App\Support\Import\ColumnMap\Column $column
     *
     * @return mixed
     */
    private function coerce(mixed $value, Column $column): mixed {
        if ($value === null || $value === '') {
            return null;
        }

        if ($column->type === Column::TYPE_BOOLEAN) {
            return $this->coerceBoolean($value);
        }

        if ($column->type === Column::TYPE_INTEGER) {
            return is_numeric($value) ? (int) $value : $value;
        }

        if ($column->type === Column::TYPE_DECIMAL) {
            return is_numeric($value) ? (float) $value : $value;
        }

        // A spreadsheet happily stores "251911223344" or "007" as a NUMBER, so a
        // text column arrives here as an int or float. Cast it back rather than
        // failing the `string` rule on data the user typed correctly.
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * A registrar types "Yes", not "1". Anything unrecognised is left as-is so
     * the boolean rule reports it rather than silently reading it as false.
     *
     * @param mixed $value
     * @return mixed
     */
    private function coerceBoolean(mixed $value): mixed {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));
        [$trueSpellings, $falseSpellings] = $this->booleanSpellings();

        if (in_array($normalized, $trueSpellings, true)) {
            return true;
        }

        if (in_array($normalized, $falseSpellings, true)) {
            return false;
        }

        return $value;
    }

    /**
     * Every accepted spelling of true and false, in every supported language.
     *
     * The localized ones are not optional. An export writes booleans using the
     * viewer's own language — an Amharic user's file says "አዎ", not "Yes" — so a
     * reader that only knew the English words would reject the very file this
     * system had just produced, and the round trip would fail for exactly the
     * users the translation was added for.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function booleanSpellings(): array {
        if ($this->booleanSpellings !== null) {
            return $this->booleanSpellings;
        }

        $true = ImportConstant::BOOLEAN_TRUE_VALUES;
        $false = ImportConstant::BOOLEAN_FALSE_VALUES;

        foreach (Message::getAvailableLangs() as $language) {
            $translations = $language::translations();

            if (isset($translations['yes'])) {
                $true[] = mb_strtolower((string) $translations['yes']);
            }

            if (isset($translations['no'])) {
                $false[] = mb_strtolower((string) $translations['no']);
            }
        }

        return $this->booleanSpellings = [array_values(array_unique($true)), array_values(array_unique($false))];
    }

    /**
     * Run the map's rules plus its business rules over one coerced row.
     *
     * Attribute names come from the map's own labels, so a message reads
     * "College code is required", localized, rather than naming a raw column.
     *
     * @param array<string, mixed> $values the coerced row
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     * @param int $rowNumber the spreadsheet row number
     *
     * @return array<int, array{row: int, column: string|null, message: string}>
     */
    private function validateRow(array $values, AbstractColumnMap $map, int $rowNumber): array {
        $labels = [];
        foreach ($map->columns() as $column) {
            $labels[$column->key] = $column->label();
        }

        $validator = Validator::make(
            $values,
            $map->rules(),
            Message::get($map->entityKey()) ?? [],
            $labels,
        );

        $errors = [];

        foreach ($validator->errors()->toArray() as $key => $messages) {
            foreach ($messages as $message) {
                $errors[] = ['row' => $rowNumber, 'column' => $key, 'message' => $message];
            }
        }

        foreach ($map->validateRow($values) as $businessError) {
            $errors[] = $this->error($rowNumber, $businessError['column'], $businessError['key']);
        }

        return $errors;
    }

    /**
     * Batch-resolve every reference column in the file: one query per column,
     * not one per row.
     *
     * @param array<int, array<string, mixed>> $rows the coerced rows
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array<string, array<string, int>> column key => (lower-cased value => id)
     */
    private function resolveReferences(array $rows, AbstractColumnMap $map): array {
        $resolved = [];

        foreach ($map->columns() as $column) {
            if (!$column->relation && !$column->lookupType && !$column->resolver) {
                continue;
            }

            $values = [];
            foreach ($rows as $row) {
                $value = $row[$column->key] ?? null;
                if ($value !== null && $value !== '') {
                    $values[mb_strtolower((string) $value)] = (string) $value;
                }
            }

            if ($column->resolver) {
                $resolved[$column->key] = ($column->resolver)($values);
                continue;
            }

            $resolved[$column->key] = $column->lookupType
                ? $this->resolveLookupValues($column, $values)
                : $this->resolveRelatedRecords($column, $values);
        }

        return $resolved;
    }

    /**
     * Resolve lookup codes to `lookup_values.id`, by stable code only.
     *
     * @param \App\Support\Import\ColumnMap\Column $column
     * @param array<string, string> $values lower-cased value => original value
     *
     * @return array<string, int>
     */
    private function resolveLookupValues(Column $column, array $values): array {
        $map = [];

        foreach ($values as $lowered => $original) {
            $id = LookupService::getValueByCode($column->lookupType, $original, needId: true);
            if ($id) {
                $map[$lowered] = (int) $id;
            }
        }

        return $map;
    }

    /**
     * Resolve a related table's stable column to its id in one query.
     *
     * @param \App\Support\Import\ColumnMap\Column $column
     * @param array<string, string> $values lower-cased value => original value
     *
     * @return array<string, int>
     */
    private function resolveRelatedRecords(Column $column, array $values): array {
        if (!$values) {
            return [];
        }

        $modelClass = $column->relation['model'];
        $lookupColumn = $column->relation['column'];

        $records = $modelClass::query()
            ->whereIn(DB::raw('lower(' . $lookupColumn . ')'), array_keys($values))
            ->pluck('id', $lookupColumn);

        $map = [];
        foreach ($records as $value => $id) {
            $map[mb_strtolower((string) $value)] = (int) $id;
        }

        return $map;
    }

    /**
     * Turn a coerced row into model attributes, reporting any reference that did
     * not resolve.
     *
     * @param array<string, mixed> $values the coerced row
     * @param array<string, array<string, int>> $references the batch-resolved maps
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     * @param int $rowNumber the spreadsheet row number
     *
     * @return array{0: array<string, mixed>, 1: array<int, array>}
     */
    private function resolveRowAttributes(array $values, array $references, AbstractColumnMap $map, int $rowNumber): array {
        $attributes = [];
        $errors = [];
        $language = getCurrentLanguage(request());

        foreach ($map->columns() as $column) {
            if ($column->exportOnly) {
                continue;
            }

            $value = $values[$column->key] ?? null;

            if ($column->relation || $column->lookupType || $column->resolver) {
                if ($value === null) {
                    $attributes[$column->attribute] = null;
                    continue;
                }

                $id = $references[$column->key][mb_strtolower((string) $value)] ?? null;

                if ($id === null) {
                    $errors[] = $this->error($rowNumber, $column->key, 'import_unresolved_reference', [
                        'value' => (string) $value,
                        'column' => $column->label(),
                    ]);
                    continue;
                }

                $attributes[$column->attribute] = $id;
                continue;
            }

            if ($column->type === Column::TYPE_TRANSLATABLE) {
                // Wrapped into the jsonb shape here; the merge with any existing
                // languages happens at write time, once the row is known.
                $attributes[$column->attribute] = $value === null ? null : [$language => $value];
                continue;
            }

            $attributes[$column->attribute] = $value;
        }

        return [$this->dropUnsetOptionals($attributes, $map), $errors];
    }

    /**
     * Drop nulls for optional columns so a create falls through to the column
     * default (`is_active` true, `can_teach` true) instead of writing null into
     * a NOT NULL column.
     *
     * @param array<string, mixed> $attributes
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array<string, mixed>
     */
    private function dropUnsetOptionals(array $attributes, AbstractColumnMap $map): array {
        foreach ($map->columns() as $column) {
            $isNullableReference = $column->relation !== null || $column->lookupType !== null || $column->resolver !== null;

            if ($column->required || $isNullableReference) {
                continue;
            }

            if (($attributes[$column->attribute] ?? null) === null && $column->type === Column::TYPE_BOOLEAN) {
                unset($attributes[$column->attribute]);
            }
        }

        return $attributes;
    }

    /**
     * Merge a translatable column with what the record already holds, so an
     * import in one language never erases the other's text (CLAUDE §11.5).
     *
     * @param array<string, mixed> $attributes the row's resolved attributes
     * @param \Illuminate\Database\Eloquent\Model $record the row being updated
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array<string, mixed>
     */
    private function mergeTranslatables(array $attributes, $record, AbstractColumnMap $map): array {
        $language = getCurrentLanguage(request());

        foreach ($map->columns() as $column) {
            if ($column->type !== Column::TYPE_TRANSLATABLE) {
                continue;
            }

            $incoming = $attributes[$column->attribute] ?? null;

            if ($incoming === null) {
                // A blank cell leaves the stored text alone rather than wiping
                // every language of it.
                unset($attributes[$column->attribute]);
                continue;
            }

            $attributes[$column->attribute] = updateLangField(
                $record->{$column->attribute},
                $language,
                $incoming[$language],
            );
        }

        return $attributes;
    }

    /**
     * The natural-key signature identifying the record a row names.
     *
     * Built from RESOLVED values so a section keys on its real
     * (program, year, level, label) tuple rather than on the codes that spell
     * it. Null when any key part failed to resolve — such a row already carries
     * an error.
     *
     * @param array<string, mixed> $values the coerced row
     * @param array<string, mixed> $attributes the resolved attributes
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return string|null
     */
    private function signature(array $values, array $attributes, AbstractColumnMap $map): ?string {
        $parts = [];

        foreach ($map->naturalKey() as $key) {
            $column = $map->column($key);
            if (!$column) {
                return null;
            }

            $value = array_key_exists($column->attribute, $attributes)
                ? $attributes[$column->attribute]
                : ($values[$key] ?? null);

            if ($value === null || $value === '') {
                // A key part the schema allows to be null is not a missing key
                // — it IS the key, matching the partial unique that covers the
                // null case.
                if (!in_array($key, $map->nullableKeyParts(), true)) {
                    return null;
                }

                $parts[] = ImportConstant::NULL_KEY_TOKEN;
                continue;
            }

            $parts[] = mb_strtolower((string) $value);
        }

        return implode('|', $parts);
    }

    /**
     * Load the records this file's natural keys already name, indexed by
     * signature.
     *
     * Soft-deleted rows are included deliberately: the unique index on `code`
     * still covers them, so a trashed college holding "COET" blocks a new one
     * and the user needs to be told that, not handed a raw constraint violation.
     *
     * @param array<int, array<string, mixed>> $rows the coerced rows
     * @param array<string, array<string, int>> $references the batch-resolved maps
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function findExistingRecords(array $rows, array $references, AbstractColumnMap $map): array {
        $modelClass = $map->modelClass();
        $naturalKey = $map->naturalKey();

        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        // One whereIn per key part narrows to a superset; the exact tuple match
        // happens on the signature below.
        foreach ($naturalKey as $key) {
            $column = $map->column($key);
            if (!$column) {
                continue;
            }

            $candidates = [];
            foreach ($rows as $row) {
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                if ($column->relation || $column->lookupType || $column->resolver) {
                    $id = $references[$column->key][mb_strtolower((string) $value)] ?? null;
                    if ($id !== null) {
                        $candidates[] = $id;
                    }
                    continue;
                }

                $candidates[] = $value;
            }

            $isNullable = in_array($key, $map->nullableKeyParts(), true);

            if (!$candidates) {
                // For a nullable part a file of blanks is a legitimate query —
                // every row is looking for the section-less record.
                if (!$isNullable) {
                    return [];
                }

                $query->whereNull($column->attribute);
                continue;
            }

            $unique = array_values(array_unique($candidates));

            $isNullable
                ? $query->where(fn ($query) => $query->whereIn($column->attribute, $unique)->orWhereNull($column->attribute))
                : $query->whereIn($column->attribute, $unique);
        }

        $existing = [];

        foreach ($query->get() as $record) {
            $parts = [];
            foreach ($naturalKey as $key) {
                $column = $map->column($key);
                $value = $record->{$column->attribute};

                $parts[] = ($value === null || $value === '')
                    ? ImportConstant::NULL_KEY_TOKEN
                    : mb_strtolower((string) $value);
            }

            $existing[implode('|', $parts)] = $record;
        }

        return $existing;
    }

    /**
     * Build one report row.
     *
     * @param int $rowNumber the spreadsheet row number
     * @param string|null $column the machine header key, when the failure has one
     * @param string $messageKey translation key
     * @param array<string, string> $bindings placeholders
     *
     * @return array{row: int, column: string|null, message: string}
     */
    private function error(int $rowNumber, ?string $column, string $messageKey, array $bindings = []): array {
        return [
            'row' => $rowNumber,
            'column' => $column,
            'message' => Message::get($messageKey, $bindings) ?? $messageKey,
        ];
    }
}
