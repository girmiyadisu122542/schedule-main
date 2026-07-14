<?php

namespace App\Http\Controllers;

use Helper\Permission\PermissionAction;
use Translation\FrontLang;

abstract class Controller {
    use PermissionAction;

    /**
     * Maximum pagination limit
     * if null indicates there is no
     * maximum pagination
     *
     * @var int|null
     */
    public const MAX_PER_PAGE = 100;

    /**
     * returns the limit for the current
     * controller
     *
     * @return int
     */
    public static function getPerPage(): int {
        if (!request()->has(PER_PAGE_KEY) || !is_numeric(request()->{PER_PAGE_KEY})) {
            return DEFAULT_PER_PAGE;
        }

        $requestedPerPage = intval(request()->{PER_PAGE_KEY});

        /** Check if the user request pagination is < 1 if so returns the DEFAULT_PER_PAGE */
        if ($requestedPerPage < 1) {
            return DEFAULT_PER_PAGE;
        }

        /** if MAX_PER_PAGE is then the user requested pagination is applied */
        if (static::MAX_PER_PAGE == null) {
            return $requestedPerPage;
        }

        /** if MAX_PER_PAGE is not null and the user requested pagination > MAX_PER_PAGE we will return the MAX_PER_PAGE */
        if ($requestedPerPage > static::MAX_PER_PAGE) {
            return static::MAX_PER_PAGE;
        }

        return $requestedPerPage;
    }

    /**
     * Get the current language from the request
     *
     * @param Request $request
     * @return string
     */
    public static function getCurrentLanguage($request): string {
        $language = $request->header('lang');
        if (!in_array($language, FrontLang::getAvailableLangKeys())) {
            $language = FrontLang::getDefaultLanguage();
        }
        return $language;
    }
}
