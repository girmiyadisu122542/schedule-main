<?php

namespace App\Support\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * The one sheet shape every export, template and sample file is written
 * through: a header row plus positional data rows, both supplied by the
 * entity's column map.
 */
class ArraySheetExport extends TextPreservingValueBinder implements FromArray, WithHeadings, WithStrictNullComparison, WithCustomCsvSettings, WithColumnFormatting, WithCustomValueBinder {
    /**
     * @param array<int, string> $headers the header row, in column order
     * @param array<int, array<int, mixed>> $rows data rows, same column order
     * @param array<int, int> $textColumnIndexes zero-based indexes to store as text
     */
    public function __construct(
        private array $headers,
        private array $rows,
        private array $textColumnIndexes = [],
    ) {
    }

    /**
     * Force text-typed columns to a text cell format.
     *
     * Without this a spreadsheet stores "+251911223344" as the NUMBER
     * 251911223344 and "007" as 7 — the leading character is gone by the time
     * the file is read back, so an exported sheet no longer round-trips. Phone
     * numbers and zero-padded codes are the everyday casualties.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array {
        $formats = [];

        foreach ($this->textColumnIndexes as $index) {
            $formats[Coordinate::stringFromColumnIndex($index + 1)] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
    }

    /**
     * The header row.
     *
     * @return array<int, string>
     */
    public function headings(): array {
        return $this->headers;
    }

    /**
     * The data rows.
     *
     * @return array<int, array<int, mixed>>
     */
    public function array(): array {
        return $this->rows;
    }

    /**
     * CSV settings.
     *
     * The BOM is the point: without it Excel opens a UTF-8 csv as Windows-1252
     * and every Amharic header and name renders as mojibake — which then fails
     * to re-import, because the header no longer matches any alias.
     *
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array {
        return [
            'use_bom' => true,
            'delimiter' => ',',
            'enclosure' => '"',
        ];
    }
}
