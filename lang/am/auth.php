<?php


return getAllModuleTranslations(
    basename(__DIR__),
    basename(__FILE__),
    [

        /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

        'failed' => 'እነዚህ ምረጃዎች ከመዝገባችን ጋር አይዛመዱም።',
        'password' => 'የተሰጠው የይለፍ ቃል ትክክል አይደለም።',
        'throttle' => 'ብዙ ጊዜ ለመግባት ሞክረዋል። እባክዎ ከ:seconds ሰከንዶች በኋላ ደግመው ይሞክሩ።',

    ]
);
