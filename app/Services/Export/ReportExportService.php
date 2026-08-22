<?php

namespace App\Services\Export;

use App\Constants\ImportConstant;
use Illuminate\Support\Str;

/**
 * Turns a schedule report into a downloadable sheet.
 *
 * Separate from {@see MasterDataExportService} because reports are not rows of
 * a table: they are computed figures with no model, no column map and no
 * importer to stay in step with. What they DO share is a shape — every report
 * returns `rows` of flat key/value records plus a `totals` block — and that is
 * enough for one exporter to serve all of them.
 *
 * Headers come from the first row's keys rather than a declared list, so a
 * report that gains a column exports it without anyone remembering to update a
 * second place. The trade is that an empty report has no columns to name; that
 * case writes a single explanatory row instead of a bare file.
 */
class ReportExportService {

    /**
     * Stream one report as a spreadsheet.
     *
     * The report array is whatever the service already computed for the JSON
     * endpoint, filters and all — so an export reflects exactly what the user
     * was looking at, which is the same guarantee the master-data export gives.
     *
     * @param array $report the `{rows, totals}` result from ScheduleReportService
     * @param string $stem filename without extension
     * @param string $format one of ImportConstant::SUPPORTED_FORMATS
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(array $report, string $stem, string $format = ImportConstant::DEFAULT_FORMAT) {
        $rows = array_values($report['rows'] ?? []);

        if (empty($rows)) {
            return app(SpreadsheetWriterService::class)->download(
                [__('No data')],
                [[__('This report returned no rows for the filters applied.')]],
                $stem,
                $format,
            );
        }

        $keys = array_keys($rows[0]);
        $headers = array_map(fn (string $key): string => Str::headline($key), $keys);

        $cells = array_map(
            fn (array $row): array => array_map(
                fn (string $key) => $this->cell($row[$key] ?? null),
                $keys,
            ),
            $rows,
        );

        // The totals belong in the file too — they are the summary the reader
        // would otherwise recompute by hand. A blank spacer keeps them from
        // reading as another data row.
        foreach ($this->totalsRows($report, count($keys)) as $extra) {
            $cells[] = $extra;
        }

        return app(SpreadsheetWriterService::class)->download($headers, $cells, $stem, $format);
    }

    /**
     * The trailing summary block, padded to the sheet's width.
     *
     * @param array $report
     * @param int $width number of columns in the data rows
     *
     * @return array<int, array<int, mixed>>
     */
    private function totalsRows(array $report, int $width): array {
        $totals = $report['totals'] ?? [];

        if (empty($totals) || !is_array($totals)) {
            return [];
        }

        $pad = fn (array $row): array => array_pad($row, $width, null);
        $rows = [$pad([])];

        foreach ($totals as $key => $value) {
            $rows[] = $pad([Str::headline((string) $key), $this->cell($value)]);
        }

        return $rows;
    }

    /**
     * Render one value as a cell.
     *
     * Booleans become Yes/No rather than 1/0 — the sheet is read by people, and
     * the importer's own boolean columns already use those words. Nested arrays
     * are flattened rather than printed as "Array".
     *
     * @param mixed $value
     * @return mixed
     */
    private function cell($value) {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => is_scalar($item) ? $item : json_encode($item), $value));
        }

        return $value;
    }
}
