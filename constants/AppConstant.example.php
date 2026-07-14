<?php

namespace Constants;

class AppConstantExample {
    public const MINIMUM_PASSWORD_LENGTH = 8;
    public const APP_URL = 'http://schedule.local';
    public const DATABASE_PASSWORD = 'password';
    public const DATABASE_USERNAME = 'postgres';
    public const DATABASE_PORT = '5432';
    public const DATABASE_HOST = '127.0.0.1';
    public const DATABASE_URL = '';
    public const SCHEDULE_DATABASE = 'schedule_user';
    public const SCHEDULE_DATABASE_PREFIX = 'schedule_user';
    public const SCHEDULE_DATABASE_CONNECTION = 'schedule_user';
    public const DB_LANGUAGES = [
        'en' => 'english',
        'am' => 'amharic',
    ];

    //urls
    public const SINGLE_NOTIFICATION_URL = '';
    public const BULK_NOTIFICATION_URL = '';

    public const NOTIFICATION_SERVICE_API_KEY = '';
    public const NOTIFICATION_SERVICE_TIMEOUT = '';
}
