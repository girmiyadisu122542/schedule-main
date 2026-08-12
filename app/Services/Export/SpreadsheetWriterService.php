<?php

namespace App\Services\Export;

use App\Constants\ImportConstant;
use App\Support\Export\ArraySheetExport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class SpreadsheetWriterService {

    /**
     * Stream a header row plus data rows as xlsx or csv.
     *
     * @param array<int, string> $headers the header row, in column order
     * @param array<int, array<int, mixed>> $rows data rows, same column order
     * @param string $filenameStem filename without extension
     * @param string $format one of ImportConstant::SUPPORTED_FORMATS
     * @param array<int, int> $textColumnIndexes zero-based indexes to store as text
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(array $headers, array $rows, string $filenameStem, string $format, array $textColumnIndexes = []) {
        $format = $this->normalizeFormat($format);

        return Excel::download(
            new ArraySheetExport($headers, $rows, $textColumnIndexes),
            $filenameStem . '.' . $format,
            $this->writerType($format),
        );
    }

    /**
     * Render the same sheet to a raw string — what the sample-file generator
     * writes to disk.
     *
     * @param array<int, string> $headers the header row, in column order
     * @param array<int, array<int, mixed>> $rows data rows, same column order
     * @param string $format one of ImportConstant::SUPPORTED_FORMATS
     * @param array<int, int> $textColumnIndexes zero-based indexes to store as text
     *
     * @return string
     */
    public function raw(array $headers, array $rows, string $format, array $textColumnIndexes = []): string {
        $format = $this->normalizeFormat($format);

        return Excel::raw(
            new ArraySheetExport($headers, $rows, $textColumnIndexes),
            $this->writerType($format),
        );
    }

    /**
     * Fall back to the default rather than trusting an arbitrary query value.
     *
     * @param string $format
     * @return string
     */
    private function normalizeFormat(string $format): string {
        $format = strtolower(trim($format));

        return in_array($format, ImportConstant::SUPPORTED_FORMATS, true)
            ? $format
            : ImportConstant::DEFAULT_FORMAT;
    }

    /**
     * Map our format slug onto the writer library's type constant.
     *
     * @param string $format
     * @return string
     */
    private function writerType(string $format): string {
        return $format === ImportConstant::FORMAT_CSV
            ? ExcelFormat::CSV
            : ExcelFormat::XLSX;
    }
}
