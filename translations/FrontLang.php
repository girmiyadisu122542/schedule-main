<?php

namespace Translation;

use Translation\Front\English;
use Common\Lang\BackLang as Lang;
use Translation\Front\Amharic;

class FrontLang extends Lang {

    /**
     * Available Language
     *
     * @var array<int, class> $langs
     */
    protected static $langs = [
        English::class,
        Amharic::class,
    ];
}
