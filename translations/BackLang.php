<?php

namespace Translation;

use Translation\Back\English;
use Common\Lang\BackLang as Lang;
use Translation\Back\Amharic;

class BackLang extends Lang {

    /**
     * Available Language
     *
     * @var array<int, class> $langs
     */
    protected static $langs = [
        English::class,
        Amharic::class
    ];
}