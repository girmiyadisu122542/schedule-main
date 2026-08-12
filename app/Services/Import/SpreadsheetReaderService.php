<?php

namespace App\Services\Import;

use App\Constants\ImportConstant;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ReadResult;
use Maatwebsite\Excel\Facades\Excel;

class SpreadsheetReaderService {

    /**
     * Read an uploaded xlsx/csv into rows keyed by the column map's machine
     * header keys.
     *
     * Headers are matched through {@see AbstractColumnMap::headerAliases()},
     * which accepts the machine key AND the localized label in every supported
     * language. That is what lets a sheet exported in Amharic — Amharic headers
     * and all — be re-imported without a translation step.
     *
     * The heading row is deliberately NOT delegated to the reader library's
     * `WithHeadingRow`: it slugifies headers to ASCII, which destroys Amharic
     * ones outright.
     *
     * @param \Illuminate\Http\UploadedFile $file the uploaded spreadsheet
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map the entity's column map
     *
     * @return \App\Support\Import\ReadResult
     */
    public function read($file, AbstractColumnMap $map): ReadResult {
        try {
            $sheets = Excel::toArray(new RawSheetReader(), $file);
        } catch (\Throwable $exception) {
            return ReadResult::failure('import_file_unreadable');
        }

        $rows = $sheets[0] ?? [];
        if (count($rows) < ImportConstant::FIRST_DATA_ROW_NUMBER) {
            return ReadResult::failure('import_file_is_empty');
        }

        $headerCells = array_shift($rows);
        $resolved = $this->resolveHeaders(is_array($headerCells) ? $headerCells : [], $map);

        if (isset($resolved['errorKey'])) {
            return ReadResult::failure($resolved['errorKey'], $resolved['bindings']);
        }

        return $this->buildRows($rows, $resolved['positions'], $map);
    }

    /**
     * Match the header row against the map, position by position.
     *
     * @param array<int, mixed> $headerCells the raw first row
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array{positions?: array<int, string>, errorKey?: string, bindings?: array<string, string>}
     */
    private function resolveHeaders(array $headerCells, AbstractColumnMap $map): array {
        $aliases = $map->headerAliases();
        $positions = [];
        $unknown = [];

        foreach ($headerCells as $index => $cell) {
            $header = trim((string) $cell);
            if ($header === '') {
                continue;
            }

            $normalized = AbstractColumnMap::normalizeHeader($header);

            if (!isset($aliases[$normalized])) {
                $unknown[] = $header;
                continue;
            }

            $positions[$index] = $aliases[$normalized];
        }

        if ($unknown) {
            return [
                'errorKey' => 'import_unknown_columns',
                'bindings' => ['columns' => implode(', ', $unknown)],
            ];
        }

        $missing = array_diff($map->requiredKeys(), array_values($positions));
        if ($missing) {
            $labels = array_map(fn (string $key) => $map->column($key)?->label() ?? $key, $missing);

            return [
                'errorKey' => 'import_missing_required_columns',
                'bindings' => ['columns' => implode(', ', $labels)],
            ];
        }

        return ['positions' => $positions];
    }

    /**
     * Turn positional cells into keyed, trimmed rows, dropping blank lines.
     *
     * @param array<int, mixed> $rows the data rows, header already removed
     * @param array<int, string> $positions cell index => machine header key
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return \App\Support\Import\ReadResult
     */
    private function buildRows(array $rows, array $positions, AbstractColumnMap $map): ReadResult {
        $keyed = [];
        $rowNumbers = [];

        foreach ($rows as $offset => $cells) {
            if (!is_array($cells)) {
                continue;
            }

            $row = [];
            $hasValue = false;

            foreach ($positions as $index => $key) {
                $value = $cells[$index] ?? null;
                $value = is_string($value) ? trim($value) : $value;

                // A cell the writer left blank and a cell the user cleared are
                // the same thing: absent.
                $row[$key] = ($value === '' ? null : $value);

                if ($row[$key] !== null) {
                    $hasValue = true;
                }
            }

            // Trailing empty lines are what spreadsheet apps leave behind, not
            // rows a user meant to import.
            if (!$hasValue) {
                continue;
            }

            // Columns the sheet omitted entirely still have to exist as keys, so
            // validation sees them as absent rather than undefined.
            foreach ($map->headerKeys() as $key) {
                $row[$key] ??= null;
            }

            $keyed[] = $row;
            $rowNumbers[] = $offset + ImportConstant::FIRST_DATA_ROW_NUMBER;

            if (count($keyed) > ImportConstant::MAX_IMPORT_ROWS) {
                return ReadResult::failure('import_too_many_rows', [
                    'max' => (string) ImportConstant::MAX_IMPORT_ROWS,
                ]);
            }
        }

        if (!$keyed) {
            return ReadResult::failure('import_file_is_empty');
        }

        return new ReadResult(rows: $keyed, rowNumbers: $rowNumbers);
    }
}
