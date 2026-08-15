<?php

namespace App\Constants;

class ImportConstant {
    /**
     * The most data rows one upload may carry (the header row is not counted).
     *
     * A registrar's real master-data sheet is hundreds of rows, not tens of
     * thousands; the ceiling exists so a mistaken 200k-row export cannot be
     * pushed back in and hold a transaction open for minutes.
     */
    public const MAX_IMPORT_ROWS = 2000;

    /** Upload ceiling in kilobytes — the `max:` on the file rule. */
    public const MAX_IMPORT_FILE_SIZE_KB = 5120;

    /** The two formats both import and export speak. */
    public const SUPPORTED_FORMATS = [self::FORMAT_XLSX, self::FORMAT_CSV];

    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_CSV = 'csv';

    /** Default when `?format=` is absent. */
    public const DEFAULT_FORMAT = self::FORMAT_XLSX;

    /** Worked example rows a generated template carries. */
    public const TEMPLATE_EXAMPLE_ROWS = 1;

    /**
     * Rows a committed sample file carries. Enough to show a real relationship
     * (several departments under one college) without becoming a fixture nobody
     * reads.
     */
    public const SAMPLE_ROWS = 4;

    /**
     * `create_only` — a row whose natural key already exists is an error.
     * `upsert`     — that row updates the existing record instead.
     */
    public const MODE_CREATE_ONLY = 'create_only';
    public const MODE_UPSERT = 'upsert';
    public const MODES = [self::MODE_CREATE_ONLY, self::MODE_UPSERT];

    /** The header row is row 1, so the first data row a user sees is row 2. */
    public const HEADER_ROW_NUMBER = 1;
    public const FIRST_DATA_ROW_NUMBER = 2;

    /**
     * Spreadsheet truthy/falsey spellings accepted for a boolean column, beyond
     * PHP's own. A registrar types "Yes", not "1".
     */
    public const BOOLEAN_TRUE_VALUES = ['1', 'true', 'yes', 'y', 't', 'active'];
    public const BOOLEAN_FALSE_VALUES = ['0', 'false', 'no', 'n', 'f', 'inactive'];

    /**
     * Stands in for a null part of a natural key.
     *
     * A sentinel no real code, label or composite cell can collide with — they
     * are alphanumerics, `-`, `/` and `|`.
     */
    public const NULL_KEY_TOKEN = '~null~';

    /** Separates repeated values inside one cell, e.g. cross-listed sections. */
    public const MULTI_VALUE_SEPARATOR = ';';

    /** Separates the parts of one composite value, e.g. `BSC-CS|2|A`. */
    public const COMPOSITE_KEY_SEPARATOR = '|';

    /**
     * Types a deliberate "empty this collection" into a cell.
     *
     * A blank cell cannot mean it: the reader cannot tell a blank from a column
     * the sheet simply did not fill, and silently un-cross-listing 40 courses is
     * not noticed until a generator double-books a room.
     */
    public const CLEAR_SENTINEL = '-';

    /** Where the committed worked samples live, relative to the repo root. */
    public const SAMPLE_DIRECTORY = '../Docs/samples/master-data';
}
