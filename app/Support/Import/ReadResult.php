<?php

namespace App\Support\Import;

/**
 * What {@see \App\Services\Import\SpreadsheetReaderService} hands back.
 *
 * A file-level failure (unreadable file, unknown header, missing required
 * header, too many rows) is not an exception — it is an expected outcome the
 * controller turns into a 422, so it travels as an error key plus the bindings
 * its translation needs. Per-ROW problems are not represented here; those are
 * the import service's pass-1 report.
 */
class ReadResult {
    /**
     * @param array<int, array<string, mixed>> $rows data rows keyed by machine header key
     * @param array<int, int> $rowNumbers spreadsheet row number for each row, same order
     * @param string|null $errorKey translation key when the file was rejected
     * @param array<string, string> $bindings placeholders for that translation
     */
    public function __construct(
        public array $rows = [],
        public array $rowNumbers = [],
        public ?string $errorKey = null,
        public array $bindings = [],
    ) {
    }

    /**
     * Did the file itself fail to read?
     *
     * @return bool
     */
    public function failed(): bool {
        return $this->errorKey !== null;
    }

    /**
     * Build a rejection.
     *
     * @param string $errorKey translation key
     * @param array<string, string> $bindings placeholders
     *
     * @return self
     */
    public static function failure(string $errorKey, array $bindings = []): self {
        return new self(errorKey: $errorKey, bindings: $bindings);
    }
}
