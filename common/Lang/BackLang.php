<?php

namespace Common\Lang;

class BackLang {
    /**
     * Available Language
     *
     * @var array<int, class> $langs
     */
    protected static $langs = [];

    /**
     * Return the available languages
     *
     * @return array<int, class>
     */
    public static function getAvailableLangs(): array {
        return static::$langs;
    }

    /**
     * Returns the available language keys
     *
     * @return array<int, string>
     */
    public static function getAvailableLangKeys(): array {
        $langs = [];
        foreach (static::getAvailableLangs() as $lang) {
            $lang = new $lang();
            array_push($langs, $lang::getKey());
        }

        return $langs;
    }

    /**
     * Returns the available language names
     *
     * @return array<int, string>
     */
    public static function getAvailableLangKeyNames(): array {
        $langs = [];
        foreach (static::getAvailableLangs() as $lang) {
            $lang = new $lang();
            $langs[$lang::getKey()] = ucfirst($lang::getName());
        }

        return $langs;
    }

    /**
     * Returns the default language
     * based on the order from $langs lists
     *
     * @return string
     */
    public static function getDefaultLanguage(): string {
        foreach (static::$langs as $lang) {
            $lang = new $lang();
            return $lang::getKey();
        }

        return "";
    }

    /**
     * Return the selected language based on
     * the user request
     *
     * @return string
     */
    public static function getLang(): string {
        if (app()->runningInConsole()) {
            return static::getDefaultLanguage();
        }
        if (request()->header('lang') && in_array(request()->header('lang'), static::getAvailableLangKeys())) {
            return request()->header('lang');
        }
        if (request('lang') && in_array(request('lang'), static::getAvailableLangKeys())) {
            return request('lang');
        }

        return static::getDefaultLanguage();
    }

    /**
     * Returns the translation based
     * on the key provided and bindings
     *
     * @param string $key
     * @param array<string, string> $bindings
     * @param string $bindingTemplate
     *
     * @return string|array|null
     */
    public static function get($key, $bindings = [], $bindingTemplate = '{{$key}}'): string|array|null {
        $lang = static::getLang();
        foreach (static::getAvailableLangs() as $language) {
            $language = new $language();
            if ($language::getKey() == $lang) {

                $translation = $language::translations()[$key] ?? null;
                if ($translation == null || count($bindings) == 0) {
                    return $translation;
                }

                return static::parseBindings($translation, $bindings, $bindingTemplate);
            }
        }

        return '_' . $key;
    }

    /**
     * Parses the bindings based on the
     * key provided and the bindingTemplate
     *
     * @return string
     */
    public static function parseBindings($translation, $bindings, $bindingTemplate): string {
        foreach ($bindings as $bindingKey => $bindingValue) {
            $bindingKey = str_replace('$key', $bindingKey, $bindingTemplate);
            $translation = str_replace($bindingKey, $bindingValue, $translation);
        }

        return $translation;
    }

    /**
     * Returns the translation message
     *
     * @param string $key
     * @return string
     */
    public static function message($key) {
        return static::get('messages', $key);
    }

    /**
     * Get all translations for a specific language
     *
     * @param string|null $langKey
     * @return array
     */
    public static function getAllTranslations(string $langKey): array {
        foreach (static::$langs as $language) {
            $instance = new $language();
            if ($instance::getKey() == $langKey) {
                return $instance::translations();
            }
        }

        return [];
    }

    /**
     * Get all available language objects
     *
     * @return array
     */
    public static function getAvailableLangObjects(): array {
        $langs = [];
        foreach (static::getAvailableLangs() as $langClass) {
            $lang = new $langClass();
            $langs[] = [
                'icon' => $lang::getIcon(),
                'code' => $lang::getKey(),
                'name' => static::get($lang::getName()),
            ];
        }
        return $langs;
    }
}
