<?php

use Constants\AppConstant;

return [
    'channels' => [
        NOTIFICATION_SEND_METHOD_EMAIL,
        NOTIFICATION_SEND_METHOD_SMS,
    ],

    'api' => [
        'single_endpoint' => AppConstant::SINGLE_NOTIFICATION_URL,
        'bulk_endpoint' => AppConstant::BULK_NOTIFICATION_URL,
        'api_key' => AppConstant::NOTIFICATION_SERVICE_API_KEY,
        'timeout' => (int) AppConstant::NOTIFICATION_SERVICE_TIMEOUT,
    ],
];
