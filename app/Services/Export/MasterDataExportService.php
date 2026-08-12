<?php

namespace App\Services\Export;

use App\Constants\ImportConstant;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\Column;
use Translation\Message;

class MasterDataExportService {

    /**
     * Stream an entity's rows as a spreadsheet.
     *
     * The query comes from the controller's own `filteredQuery()` — the same
     * builder its `index` paginates. Export therefore honours whatever the user
     * has filtered to: someone who narrowed the list to one college exports that
     * college, not all 400 rows. Nothing about the filters is restated here.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query the controller's filtered builder
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map the entity's column map
     * @param string $format one of ImportConstant::SUPPORTED_FORMATS
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export($query, AbstractColumnMap $map, string $format) {
        $records = $query->with($map->exportWith())->get();

        $rows = $records
            ->map(fn ($record) => $this->rowFor($record, $map))
            ->all();

        return app(SpreadsheetWriterService::class)->download(
            $map->headers(),
            $rows,
            $this->filenameStem($map),
            $format,
            $map->textColumnIndexes(),
        );
    }

    /**
     * Build the downloadable template: the header row plus a worked example.
     *
     * Generated from the column map at request time, so it cannot drift from
     * what the importer accepts — which is the entire reason the map exists.
     *
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map the entity's column map
     * @param string $format one of ImportConstant::SUPPORTED_FORMATS
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function template(AbstractColumnMap $map, string $format) {
        return app(SpreadsheetWriterService::class)->download(
            $map->headers(),
            $map->exampleRows(),
            $this->filenameStem($map) . '-template',
            $format,
            $map->textColumnIndexes(),
        );
    }

    /**
     * Render one record into its cells, in column order.
     *
     * @param \Illuminate\Database\Eloquent\Model $record
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     *
     * @return array<int, mixed>
     */
    public function rowFor($record, AbstractColumnMap $map): array {
        return array_map(
            fn (Column $column) => $this->cell($column->export($record), $column),
            $map->columns(),
        );
    }

    /**
     * Render one cell so that what comes out re-imports unchanged.
     *
     * Booleans are written as the same Yes/No spellings the importer accepts —
     * writing raw `true` would round-trip through the truthy list fine, but a
     * registrar opening the file should see the vocabulary they are expected to
     * type back.
     *
     * @param mixed $value the raw exported value
     * @param \App\Support\Import\ColumnMap\Column $column
     *
     * @return mixed
     */
    private function cell(mixed $value, Column $column): mixed {
        if ($column->type === Column::TYPE_BOOLEAN) {
            if ($value === null) {
                return null;
            }

            return $value ? Message::get('yes') : Message::get('no');
        }

        if ($column->type === Column::TYPE_DECIMAL && $value !== null) {
            // Trim the stored decimal's trailing zeros: "3.00" reads as 3.
            return (float) $value;
        }

        return $value;
    }

    /**
     * Filename stem — the entity slug plus today's date.
     *
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     * @return string
     */
    private function filenameStem(AbstractColumnMap $map): string {
        return $map->entityKey() . 's-' . now()->format('Y-m-d');
    }

    /**
     * The formats this service can write.
     *
     * @return array<int, string>
     */
    public function supportedFormats(): array {
        return ImportConstant::SUPPORTED_FORMATS;
    }
}
