<?php

namespace App\Http\Controllers\Concerns;

use App\Constants\ImportConstant;
use App\Http\Requests\Import\ImportRequest;
use App\Services\Export\MasterDataExportService;
use App\Services\Import\MasterDataImportService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * The three import/export endpoints every master-data controller gains.
 *
 * The entity-specific parts are the four hooks below, each implemented as a
 * literal in the host controller — no string-keyed registry and no `match` on
 * an entity name, so a renamed permission or map fails at compile time rather
 * than at runtime.
 */
trait HandlesMasterDataImportExport {

    /**
     * The entity's column map — the single definition of its sheet.
     *
     * @return \App\Support\Import\ColumnMap\AbstractColumnMap
     */
    abstract protected function columnMap(): AbstractColumnMap;

    /**
     * The SAME builder `index` paginates, filters and all.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function filteredQuery(Request $request);

    /**
     * `export:<entity>`.
     *
     * @return bool
     */
    abstract protected function canExportEntity(): bool;

    /**
     * `import:<entity>`. Gates the template download too — a template is only
     * useful to someone who may import.
     *
     * @return bool
     */
    abstract protected function canImportEntity(): bool;

    /**
     * Download the filtered list as xlsx or csv.
     *
     * Gated here rather than in a Form Request because the endpoint has no body
     * (CLAUDE §10.17).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function export(Request $request) {
        if (!$this->canExportEntity()) {
            return Response::_403();
        }

        return app(MasterDataExportService::class)->export(
            $this->filteredQuery($request),
            $this->columnMap(),
            (string) $request->input('format', ImportConstant::DEFAULT_FORMAT),
        );
    }

    /**
     * Download the blank template — headers plus one worked example row, built
     * from the column map at request time so it can never go stale.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function importTemplate(Request $request) {
        if (!$this->canImportEntity()) {
            return Response::_403();
        }

        return app(MasterDataExportService::class)->template(
            $this->columnMap(),
            (string) $request->input('format', ImportConstant::DEFAULT_FORMAT),
        );
    }

    /**
     * Import a spreadsheet. Authorization is the Form Request's job here, since
     * this endpoint has a body.
     *
     * NOT the routed action: each controller declares its own `import()` naming
     * its CONCRETE Form Request, then calls this. The route cannot type-hint
     * `ImportRequest` itself — it is abstract, and the container would fail to
     * build it ("Target [ImportRequest] is not instantiable") long before
     * `authorize()` ever ran, turning every unauthorized call into a 500.
     *
     * @param \App\Http\Requests\Import\ImportRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleImport(ImportRequest $request): JsonResponse {
        try {
            $result = app(MasterDataImportService::class)->import(
                $request->file('file'),
                $this->columnMap(),
                $request->validated(),
            );
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_import_file'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $isDryRun = $request->boolean('dry_run');
        $hasErrors = !empty($result['errors']);

        // A dry run always reports 200 — it is a preview, and "3 rows would
        // fail" is a successful answer to the question the caller asked.
        if (!$isDryRun && $hasErrors && $result['created'] === 0 && $result['updated'] === 0) {
            return Response::_422([
                'data' => $result,
                'message' => Message::get('import_rejected_no_rows_written'),
            ]);
        }

        return Response::_200([
            'data' => $result,
            'message' => $this->importMessage($result, $isDryRun),
        ]);
    }

    /**
     * The summary line shown when the import returns.
     *
     * @param array $result the import report
     * @param bool $isDryRun
     *
     * @return string|null
     */
    private function importMessage(array $result, bool $isDryRun): ?string {
        $bindings = [
            'created' => (string) $result['created'],
            'updated' => (string) $result['updated'],
            'skipped' => (string) $result['skipped'],
        ];

        return $isDryRun
            ? Message::get('import_dry_run_completed', $bindings)
            : Message::get('import_completed', $bindings);
    }
}
