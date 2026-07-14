<?php

namespace Translation;

use Common\Lang\BackLang as Lang;
use Translation\Sidebar\Amharic;
use Translation\Sidebar\English;

class SidebarLang extends Lang {

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
