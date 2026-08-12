<?php

namespace App\Services\Import;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * A no-op import object, so `Excel::toArray()` hands back the sheet exactly as
 * written — positional cells, no heading-row slugification.
 *
 * The heading row is mapped by {@see SpreadsheetReaderService} instead, against
 * the column map's alias table. Doing it here through `WithHeadingRow` would
 * slugify headers to ASCII and destroy Amharic ones.
 */
class RawSheetReader implements ToArray {
    /**
     * Required by the contract; the rows are read from the return of
     * `Excel::toArray()`, not from here.
     *
     * @param array $array the parsed sheet
     * @return void
     */
    public function array(array $array): void {
    }
}
