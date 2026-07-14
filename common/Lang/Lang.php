<?php

namespace Common\Lang;

use Translation\BackLang;
use Translation\FrontLang;
use Translation\SidebarLang;

class Lang {
    /**
     * The key or identifier of the language
     *
     * @var string
     */
    protected static $key;

    /**
     * The name of the language that will be shown
     * in the dropdown when changing language
     * @var string
     */
    protected static $name;

    /**
     * The icon of the language that will be displayed
     * in the dropdown next to the name of the language
     *
     * @var string
     */
    protected static $icon;

    /**
     * Return the key of the language
     *
     * @return string
     */
    public static function getKey(): string {
        return static::$key;
    }

    /**
     * Return the name of the language
     *
     * @return string
     */
    public static function getName(): string {
        return static::$name;
    }

    /**
     * Return the icon of the language
     *
     * @return string
     */
    public static function getIcon(): string {
        return static::$icon;
    }

    /**
     * holds the language
     * translations
     *
     * @return array<string, string>
     */
    public static function translations(): array {
        return [];
    }

    /**
     * returns the user specified language
     *
     * @return string
     */
    public static function getUserLang(): string {
        return request('lang');
    }


    /**
     * Get all available sidebar translations
     *
     * @return array
     */
    public static function getSidebarMergedLanguage(): array {
        $language = SidebarLang::getLang();

        return [...SidebarLang::getAllTranslations($language)];
    }

    /**
     * Get all available front translations
     *
     * @return array
     */
    public static function getFrontMergedLanguage(): array {
        $language = FrontLang::getLang();

        return [...FrontLang::getAllTranslations($language)];
    }

    /**
     * Get all available back translations
     *
     * @return array
     */
    public static function getBackMergedLanguage(): array {
        $language = BackLang::getLang();

        return [...BackLang::getAllTranslations($language)];
    }
}
