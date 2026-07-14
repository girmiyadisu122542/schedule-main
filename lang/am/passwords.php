<?php


return getAllModuleTranslations(
    basename(__DIR__),
    basename(__FILE__),
    [

        /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

        'reset' => 'የይለፍ ቃልዎ ተራዘመ።',
        'sent' => 'የይለፍ ቃል ለመራዘም ያለው አገናኝ በኢሜይል ልከናል።',
        'throttled' => 'እባክዎ ከመሞከር ቀድሞ ይጠብቁ።',
        'token' => 'ይህ የይለፍ ቃል ለመራዘም ቶክን ልክ አይደለም።',
        'user' => 'በዚህ ኢሜይል አድራሻ ለሆነ ተጠቃሚ ማግኘት አልተሳካል።',

    ]
);
