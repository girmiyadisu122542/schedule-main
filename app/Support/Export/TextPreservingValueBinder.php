<?php

namespace App\Support\Export;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Writes PHP strings as spreadsheet TEXT, leaving real numbers as numbers.
 *
 * The default binder inspects the string and "helpfully" converts anything
 * numeric-looking, which silently damages exactly the values a master-data sheet
 * depends on:
 *
 *   "+251911223344"  becomes the number 251911223344 — the plus is gone
 *   "007"            becomes 7                        — the padding is gone
 *   "2025/26"        becomes a date
 *
 * Every one of those then fails to resolve on re-import, which is how an export
 * stops round-tripping. Column formatting alone does not fix it: that sets how a
 * cell is DISPLAYED, after the value has already been stored as a number.
 *
 * The rows this binder sees come from a column map, where textual columns hold
 * PHP strings and numeric ones hold PHP ints/floats — so binding on the PHP type
 * is exactly the right signal.
 */
class TextPreservingValueBinder extends DefaultValueBinder {
    /**
     * Bind a value to a cell.
     *
     * @param \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
     * @param mixed $value
     *
     * @return bool
     */
    public function bindValue(Cell $cell, mixed $value): bool {
        if (is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
