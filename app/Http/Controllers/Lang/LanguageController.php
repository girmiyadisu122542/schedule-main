<?php

namespace App\Http\Controllers\Lang;

use App\Http\Controllers\Controller;
use Common\Lang\Lang;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\FrontLang;

class LanguageController extends Controller {
    /**
     * Fetch all translations for the current language
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTranslations(Request $request): JsonResponse {
        $currentLang = $this->getCurrentLanguage($request);

        $availableLangs = FrontLang::getAvailableLangObjects();
        $translations = Lang::getFrontMergedLanguage();

        return Response::_200([
            'current_language' => $currentLang,
            'available_languages' => $availableLangs,
            'translations' => $translations,
        ]);
    }
}
