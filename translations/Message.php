<?php

namespace Translation;

use Translation\Message\English;
use Common\Lang\BackLang as Lang;
use Translation\Message\Amharic;

class Message extends Lang {

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