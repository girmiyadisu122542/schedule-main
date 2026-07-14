<?php

use App\Faker\AmharicTranslator;
use App\Models\Common\Lookup\LookupValue;
use App\Services\Lookup\LookupService;
use Helper\Permission\PermissionActionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Translation\FrontLang;

if (!function_exists('getAllModuleTranslations')) {
    /**
     * Merge core and all module translation arrays for a given locale and file.
     *
     * @param string $locale
     * @param string $fileName (e.g. 'validation.php', 'pagination.php')
     * @param array $core (optional) The core translation array
     * @return array
     */
    function getAllModuleTranslations(string $locale, string $fileName, array $core = []): array {
        $translations = $core;
        $baseDir = realpath(__DIR__ . '/../modules');
        if ($baseDir === false) {
            return $translations;
        }
        $pattern = $baseDir . '/*/lang/' . $locale . '/' . $fileName;
        $files = glob($pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                $moduleTranslations = include $file;
                if (is_array($moduleTranslations)) {
                    $translations = array_replace_recursive($translations, $moduleTranslations);
                }
            }
        }
        return $translations;
    }
}
if (!function_exists('autoTranslate')) {

    function autoTranslate(string $text, string $lang): string {
        $lang = strtolower($lang);
        if ($lang === 'en' || trim($text) === '') {
            return $text;
        }

        $url = "https://translate.googleapis.com/translate_a/single"
            . "?client=gtx&sl=en&tl={$lang}&dt=t&q=" . rawurlencode($text);

        if (app()->runningInConsole()) {
            echo "[autoTranslate] {$text} → {$lang}\n";
        }

        $response = @file_get_contents($url);
        if ($response === false) {
            return $text;
        }

        $json = json_decode($response, true);
        if (!is_array($json) || !isset($json[0])) {
            return $text;
        }

        $translated = '';
        foreach ($json[0] as $segment) {
            if (isset($segment[0])) {
                $translated .= $segment[0];
            }
        }

        return trim($translated) !== '' ? trim($translated) : $text;
    }
}


if (!function_exists('getCurrentLanguage')) {
    /**
     * Get the current language from the request
     *
     * @param Request $request
     * @return string
     */
    function getCurrentLanguage($request): string {
        $language = $request->header('lang')
            ?? $request->header('language')
            ?? $request->input('lang')
            ?? $request->input('language');
        $language = is_string($language) ? strtolower(trim($language)) : '';

        if ($language && !in_array($language, FrontLang::getAvailableLangKeys(), true)) {
            if (str_starts_with($language, 'am')) {
                $language = 'am';
            } elseif (str_starts_with($language, 'en')) {
                $language = 'en';
            }
        }

        if (!$language || !in_array($language, FrontLang::getAvailableLangKeys(), true)) {
            $language = FrontLang::getDefaultLanguage();
        }

        return $language;
    }
}

if (!function_exists('getLocalizedValue')) {
    /**
     * Get localized value from a JSON-casted array with fallback.
     *
     * @param array|null $array
     * @param string|null $language
     * @return string|null
     */
    function getLocalizedValue($array, $language = null): ?string {
        $language = $language ?? getCurrentLanguage(request());

        if (!is_array($array) || empty($array)) {
            return null;
        }
        if (isset($array[$language])) {
            return $array[$language];
        }
        $defaultLang = FrontLang::getDefaultLanguage();
        if (isset($array[$defaultLang])) {
            return $array[$defaultLang];
        }
        return reset($array);
    }
}

if (!function_exists('updateLangField')) {
    /**
     * Merge or update a language-keyed array field.
     * @param array|null $existing
     * @param string $lang
     * @param mixed $value
     * @return array|null
     */

    function updateLangField(?array $existing, string $lang, $value, bool $canBeNull = false): ?array {
        $existing = $existing ?? [];

        if ($value === null) {
            if ($canBeNull) {
                unset($existing[$lang]);
                return $existing;
            }
            return $existing;
        }

        $existing[$lang] = $value;
        return $existing;
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($name, $modelClass, $field = 'slug'): string {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = Str::uuid();
        }
        $count = 0;

        $hasSoftDelete = method_exists($modelClass, 'withTrashed');
        while ($modelClass::when($hasSoftDelete, fn ($query) => $query->withTrashed())->withoutGlobalScopes()->where($field, $slug)->exists()) {
            $count++;
            $slugBase = empty(Str::slug($name)) ? 'item-' . Str::random(6) : Str::slug($name);
            $slug = $slugBase . '-' . $count;
        }

        return $slug;
    }
}

if (!function_exists('getFileUrl')) {
    /**
     * GEt a file url from a given storage.
     * @param string $storage
     * @param string $fileName
     * @return mixed
     */
    function getFileUrl(?string $storage, ?string $fileName): string | null {
        if (empty($storage) || !$storage) {
            return DEFAULT_STORAGE;
        }

        if (empty($fileName) || !$fileName) {
            return null;
        }

        try {
            return Storage::disk($storage)->url($fileName) ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($name, $modelClass, $field = 'slug'): string {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = Str::uuid();
        }
        $count = 0;

        while ($modelClass::where($field, $slug)->exists()) {
            $count++;
            $slugBase = empty(Str::slug($name)) ? 'item-' . Str::random(6) : Str::slug($name);
            $slug = $slugBase . '-' . $count;
        }

        return $slug;
    }
}

if (!function_exists('generateRandomNumber')) {
    /**
     * Get a random number of a specific length
     * @param int $length
     * @return string
     */
    function generateRandomNumber(int $length): string {
        return Str::padLeft((string) random_int(0, (10 ** $length) - 1), $length, '0');
    }
}

if (!function_exists('formatToTwoDecimals')) {
    /**
     * echo formatToTwoDecimals(5);        // 5.00
     * echo formatToTwoDecimals(5.1);      // 5.10
     * echo formatToTwoDecimals(5.6789);   // 5.68
     */
    function formatToTwoDecimals(float $number): string {
        return number_format($number, 2, '.', '');
    }
}

if (!function_exists('toEnglish')) {
    /**
     * echo toEnglish('በርናባስ');  // bernabas
     * echo toEnglish('አወል');  // Awol
     * echo toEnglish('አረጋዊ');  // aregawi
     */
    function toEnglish(string $text): string {
        return (new AmharicTranslator())->toEnglish($text);
    }
}

if (!function_exists('toAmharic')) {
    /**
     * echo toAmharic('natnael'); // ናትናኤል
     * echo toAmharic('amare');  // አማረ
     * echo toAmharic('abebe');  // አበበ
     */
    function toAmharic(string $text): string {
        return (new AmharicTranslator())->toAmharic($text);
    }
}


if (!function_exists('consoleInfo')) {
    function consoleInfo(string $message): void {
        if (app()->runningInConsole()) {
            echo "\033[1;32m {$message}\033[0m\n";
        }
    }
}

if (!function_exists('consoleSuccess')) {
    function consoleSuccess(string $message): void {
        if (app()->runningInConsole()) {
            echo "\033[1;32m✅ {$message}\033[0m\n";
        }
    }
}

if (!function_exists('consoleError')) {
    function consoleError(string $message): void {
        if (app()->runningInConsole()) {
            echo "\033[1;31m❌ {$message}\033[0m\n";
        }
    }
}

if (!function_exists('consoleWarn')) {
    function consoleWarn(string $message): void {
        if (app()->runningInConsole()) {
            echo "\033[1;33m⚠️  {$message}\033[0m\n";
        }
    }
}

if (!function_exists('resolveCalendarPreference')) {

    function resolveCalendarPreference($calendarSystemInput): int {
        $calendarSystemInput = $calendarSystemInput ?? request()->header('calendar_preference');

        return match ($calendarSystemInput) {
            CALENDAR_SYSTEM_GREGORIAN, 'gregorian' => CALENDAR_SYSTEM_GREGORIAN,
            CALENDAR_SYSTEM_ETHIOPIAN, 'ethiopian' => CALENDAR_SYSTEM_ETHIOPIAN,
            default => CALENDAR_SYSTEM_GREGORIAN,
        };
    }
}

if (!function_exists('mask_value')) {
    /**
     * Universal masking function (email, phone, string).
     */
    function mask_value(
        string $value,
        string $type = 'string',
        array $options = []
    ): string {

        $defaults = [
            'email_visible' => 2,
            'phone_visible' => 3,
            'string_start' => 2,
            'string_end' => 2,
        ];

        $opts = array_merge($defaults, $options);

        // Small helper for repeating mask
        $mask = fn ($len) => str_repeat('*', max(0, $len));

        if ($type === 'email') {
            if (!str_contains($value, '@')) {
                return $value;
            }

            [$name, $domain] = explode('@', $value);

            $visible = min($opts['email_visible'], strlen($name));
            $hidden = $mask(strlen($name) - $visible);

            return substr($name, 0, $visible) . $hidden . '@' . $domain;
        }

        if ($type === 'phone') {
            $digits = preg_replace('/\D/', '', $value);

            if (strlen($digits) <= $opts['phone_visible']) {
                return $value;
            }

            return $mask(strlen($digits) - $opts['phone_visible'])
                . substr($digits, -$opts['phone_visible']);
        }

        $len = strlen($value);
        $start = $opts['string_start'];
        $end = $opts['string_end'];

        if ($len <= ($start + $end)) {
            return $value;
        }

        return substr($value, 0, $start)
            . $mask($len - ($start + $end))
            . substr($value, -$end);
    }
}

if (!function_exists('uploadSingleFile')) {
    function uploadSingleFile(UploadedFile $file, string $directory = 'uploads', string $disk = 'public'): string {
        $originalName = time() . '_' . $file->getClientOriginalName();
        return $file->storeAs($directory, $originalName, $disk);
    }
}

if (!function_exists('uploadMultipleFiles')) {
    function uploadMultipleFiles(array $files, string $directory = 'uploads', string $disk = 'public'): array {
        $paths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $originalName = time() . '_' . $file->getClientOriginalName();
                $paths[] = $file->storeAs($directory, $originalName, $disk);
            }
        }

        return $paths;
    }
}

if (!function_exists('downloadFile')) {
    function downloadFile(string $path, string $name, string $disk = 'public') {
        return Storage::disk($disk)->download($path, $name);
    }
}

if (!function_exists('deleteFile')) {
    function deleteFile(string $path, string $disk = 'public'): bool {
        return Storage::disk($disk)->delete($path);
    }
}

if (!function_exists('deleteMultipleFiles')) {
    function deleteMultipleFiles(array $paths, string $disk = 'public'): bool {
        return Storage::disk($disk)->delete($paths);
    }
}



if (!function_exists('cleanupStoredFiles')) {
    function cleanupStoredFiles(array|string $paths, string $disk = DEFAULT_STORAGE): void {
        $paths = is_array($paths) ? $paths : array_filter([(string) $paths]);

        if (empty($paths)) {
            return;
        }

        $storage = Storage::disk($disk);

        foreach ($paths as $path) {
            if ($path && $storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }


    if (!function_exists('isDropdownEnabled')) {
        /**
         * Check if the dropdown parameter in the request is enabled.
         *
         * @return bool
         */
        function isDropdownEnabled(): bool {
            return request()->boolean('dropdown', false);
        }
    }
}

if (!function_exists('isDatePlusDaysFuture')) {
    /**
     * Check if (date + days) is greater than or equal to today
     *
     * @param string|DateTimeInterface $dateInput
     * @param int $days
     * @return bool
     */
    function isDatePlusDaysFuture($dateInput, int $days): bool {
        try {
            $date = $dateInput instanceof DateTimeInterface
                ? Carbon::instance($dateInput)
                : Carbon::parse($dateInput);
        } catch (Exception $e) {
            return false;
        }

        $expiryDate = $date->copy()->addDays($days)->startOfDay();
        $today = Carbon::today();

        return $expiryDate->greaterThanOrEqualTo($today);
    }
}

if (!function_exists('isDateTodayOrFuture')) {
    /**
     * Check if a date is today or in the future
     *
     * @param string|DateTimeInterface $dateInput
     * @return bool
     */
    function isDateTodayOrFuture($dateInput): bool {
        try {
            $date = $dateInput instanceof DateTimeInterface
                ? Carbon::instance($dateInput)
                : Carbon::parse($dateInput);
        } catch (Exception $e) {
            return false;
        }

        return $date->startOfDay()->greaterThanOrEqualTo(Carbon::today());
    }
}


if (!function_exists('getPermissionsFromRequest')) {
    /**
     * Extracts, decrypts, and converts permissions from the request.
     * Accepts either a single encrypted permission or an array of them.
     *
     * @param string $key The request key to look for (default: 'permissions')
     * @return array Decrypted permission values
     */
    function getPermissionsFromRequest(string $key = DEFAULT_PERMISSION_KEY): array {
        $permissions = request($key);
        if ((bool) request('is_back')) {
            return [$permissions];
        }

        if (is_null($permissions)) {
            return [];
        }

        if (is_array($permissions)) {
            return array_map(function ($encrypted) {
                return PermissionActionHelper::decryptAndConvertToPermission($encrypted);
            }, $permissions);
        }
        return [PermissionActionHelper::decryptAndConvertToPermission($permissions)];
    }
}


if (!function_exists('generateCode')) {
    /**
     * Generate a code string from a given name with configurable options.
     *
     * Formats:
     *   - 'snake'    =>   "user_management" (default)
     *   - 'upper_snake' => "USER_MANAGEMENT"
     *   - 'slug' =>"user-management"
     *   - 'upper' => "USERMANAGEMENT"
     *   - 'abbr' => "UM"  (first letter of each word, uppercased)
     *   - 'prefix' => "USR_001" (prefix + separator + padded counter)
     *
     * @param string $name    Source string to derive the code from
     * @param string $format  One of: snake, upper_snake, slug, upper, abbr, prefix
     * @param array  $options Extra options:
     *                          - 'prefix'    (string) Used with format=prefix
     *                          - 'separator' (string) Separator for prefix format, default '_'
     *                          - 'counter'   (int)    Counter value for prefix format
     *                          - 'pad'       (int)    Zero-pad width for counter, default 3
     *                          - 'unique'    (bool)   Append suffix until unique in DB, default false
     *                          - 'model'     (string) Model class for uniqueness check
     *                          - 'field'     (string) DB column to check, default 'code'
     * @return string
     */
    function generateCode(string $name, string $format = CODE_FORMAT_SNAKE, array $options = []): string {
        $separator = $options[CODE_OPT_SEPARATOR] ?? '_';
        $pad = $options[CODE_OPT_PAD] ?? 3;
        $unique = $options[CODE_OPT_UNIQUE] ?? false;
        $model = $options[CODE_OPT_MODEL] ?? null;
        $field = $options[CODE_OPT_FIELD] ?? 'code';

        $code = match ($format) {
            CODE_FORMAT_UPPER_SNAKE => Str::upper(Str::snake($name)),
            CODE_FORMAT_SLUG => Str::slug($name),
            CODE_FORMAT_UPPER_SLUG => Str::upper(Str::slug($name)),
            CODE_FORMAT_UPPER => Str::upper(preg_replace('/[^a-zA-Z0-9]/', '', $name)),
            CODE_FORMAT_ABBR => Str::upper(implode('', array_map(
                fn ($w) => $w[0] ?? '',
                preg_split('/[\s_\-]+/', trim($name))
            ))),
            CODE_FORMAT_PREFIX => Str::upper($options[CODE_OPT_PREFIX] ?? Str::limit(Str::snake($name), 3, ''))
                . $separator
                . str_pad((string) ($options[CODE_OPT_COUNTER] ?? 1), $pad, '0', STR_PAD_LEFT),
            default => Str::snake($name),
        };

        if (!$unique || !$model || !class_exists($model)) {
            return $code;
        }

        $base = $code;
        $attempt = $code;
        $i = 1;

        $hasSoftDelete = method_exists($model, 'withTrashed');
        while ($model::when($hasSoftDelete, fn ($query) => $query->withTrashed())->where($field, $attempt)->exists()) {
            $attempt = $base . $separator . $i++;
        }

        return $attempt;
    }
}

if (!function_exists('buildModelPath')) {
    /**
     * Build materialized path for ANY model
     *
     * @param string $modelClass
     * @param int|null $parentId
     * @param int $currentId
     * @param string $pathColumn
     *
     * @return string
     */
    function buildModelPath(string $modelClass, ?int $parentId, int $currentId, string $pathColumn = 'path'): string {
        if (empty($parentId)) {
            return '/' . $currentId;
        }

        $parent = $modelClass::query()
            ->select('id', $pathColumn)
            ->find($parentId);

        if (!$parent) {
            return '/' . $currentId;
        }

        return $parent->{ $pathColumn } . '/' . $currentId;
    }
}

if (!function_exists('resolveLevel')) {

    /**
     * Resolve the level of a location  based on its parent location .
     * If the level is not provided, it defaults to 1.
     *
     * @param int|null $parentId
     * @param string $modelClass
     * @param string $column
     * @param int $initialLevel
     *
     * @return int
     */
    function resolveLevel(string $modelClass, ?int $parentId, ?string $column = 'level', ?int $initialLevel = 1): int {
        if ($parentId) {
            $parentLevel = $modelClass::query()
                ->where('id', $parentId)
                ->value($column);

            if (!$parentLevel) {
                return $initialLevel;
            }

            return ((int) $parentLevel) + 1;
        }

        return  $initialLevel;
    }
}

if (!function_exists('determineMediaType')) {
    /**
     * Determines the media type based on the given MIME type.
     *
     * @param string $mime
     * @return string
     */
    function determineMediaType(string $mime): string {
        foreach (MEDIA_TYPES as $mediaType => $typeList) {
            foreach ($typeList as $type) {
                if (str_starts_with($mime, $type)) {
                    return $mediaType;
                }
            }
        }

        return MEDIA_TYPE_UNKNOWN;
    }
}

if (!function_exists('numberToArray')) {
    /**
     * Resolve ID (Number (int)) to array
     *
     * @param mixed $id
     * @return array|null
     */
    function numberToArray($id): array|null {
        if ($id !== null) {
            if (!is_array($id)) {
                $id = [$id];
            }

            if (count($id) == 0) {
                $id = null;
            }
        }

        return $id;
    }
}

if (!function_exists('validateLookupValue')) {
    /**
     * Validate if a given ID is a valid lookup value ID.
     *
     * @param string $typeCode
     * @return array
     */
    function validateLookupValue(string $typeCode, ?bool $isRequired = false): array {
        return [
            $isRequired ? 'required' : 'nullable',
            'integer',
            function ($attribute, $value, $fail) use ($typeCode) {

                $lookupValue = LookupValue::query()
                    ->where('id', $value)
                    ->first();

                if (!$lookupValue) {
                    $fail(__('validation.invalid_lookup_value', [
                        'attribute' => $attribute,
                    ]));

                    return;
                }

                $lookupValueId = LookupService::getValueByCode(
                    $typeCode,
                    $lookupValue->code
                );

                if ($lookupValueId === null) {
                    $fail(__('validation.invalid_lookup_value', [
                        'attribute' => $attribute,
                    ]));
                }
            },
        ];
    }
}

if (!function_exists('generateSequenceNumber')) {
    /**
     * Generate formatted sequence number.
     *
     * Example:
     * generateSequenceNumber(6, 123)
     * Returns: 000124
     *
     * @param int $digits
     * @param int $currentNumber
     *
     * @return string|false
     */
    function generateSequenceNumber(int $digits, int $currentNumber): string|false {
        $nextNumber = $currentNumber + 1;
        $nextNumberLength = strlen((string) $nextNumber);

        if ($nextNumberLength > $digits) {
            return false;
        }

        return str_pad(
            string: (string) $nextNumber,
            length: $digits,
            pad_string: '0',
            pad_type: STR_PAD_LEFT
        );
    }
}

if (!function_exists('hashLabel')) {
    /**
     * Build a "#<value>" reference label (fallback display when no name/label
     * resolves, e.g. "#123"). Centralised so the prefix is changed in one place.
     *
     * @param int|string $value
     * @return string
     */
    function hashLabel(int|string $value): string {
        return '#' . $value;
    }
}

if (!function_exists('formatText')) {
    /**
     * Format given text
     * E.g $value = WITHHOLD_TRANSACTION_TYPE
     * returns "Withhold Transaction Type" if $allFirstLetterCaps = true
     * returns "Withhold transaction type" else
     *
     * @param string $value
     * @param bool $allFirstLetterCaps
     *
     * @return string
     */
    function formatText(string $value, bool $allFirstLetterCaps = false): string {
        $words = str_replace('_', ' ', strtolower($value));

        return $allFirstLetterCaps
            ? ucwords($words)
            : ucfirst($words);
    }
}

