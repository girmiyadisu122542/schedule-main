<?php

define('MODULE_MIGRATION_TRACKING_TABLE', 'module_migrations');

/** Relational Data deletion dependency tracking and actions */
define('MODULE_DATABASE_RELATION_DEPENDENCY_CACHE_KEY', 'module.dependencies');
define('MODULE_DATABASE_RELATION_DEPENDENCY_CACHE_TIME', 1); // In seconds
define('RESTRICT_DELETE', 'restrict');
define('DELETE_ALL_RELATED_DATA', 'cascade');
define('NULLIFY_RELATED_DATA', 'set_null');
define('SET_SPECIFIC_VALUE', 'set_value');
define('USE_CUSTOM_METHOD', 'custom');

define('SECURE_FILE_ROUTE', 'files.secure.serve');
define('SECURE_FILE_ALLOWED_EXTENSIONS', ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp']);
define('CACHE_TTL', 3600); // 1 hour

define('ENTITY_PATH_DELIMITER', '/');

// Barcode generation
define('BARCODE_GENERATED_LENGTH', 10);

/** Sentry Related */
define('SENTRY_ENABLED', false);
define('SENTRY_DSN', 'http://8aac3714fd65abb1cd7988caab77f141@196.188.247.61:9000/3');

define('DEVELOPMENT_NOTIFICATION_SERVER', 'http://196.188.247.38');

/** Redis related */
define('CAN_USE_REDIS', false); // make sure you have installed redis on your machine
define('USE_HORIZON_FOR_QUEUE', false); // enable this when you are sure you have installed redis on your machine.

define('CORE_MODULE_CODE', 'UserMgmt-0001');
define('CUSTOMIZATION_MODULE_CODE', 'MOD-CUSTOMIZATION-0001');
define('SYSTEM_CONFIG_MODULE_CODE', 'MOD-SYSTEM-0001');
define('INVENTORY_MODULE_CODE', 'MOD-INVENTORY-0001');
define('SALES_MODULE_CODE', 'MOD-SALES-0001');
define('SALES_AND_CUSTOMER_MODULE_CODE', 'SALES&_C_R_M');
define('INTEGRATION_MODULE_CODE', 'MOD-INTEGRATION-0001');

/**
 * Platform-administration module codes. Sidebar groups tagged with one of these
 * are part of the core platform (not a subscribable business module), so they
 * stay visible regardless of which business module is currently selected.
 */
define('SYSTEM_ADMIN_MODULE_CODES', [
    CORE_MODULE_CODE,
    SYSTEM_CONFIG_MODULE_CODE,
    CUSTOMIZATION_MODULE_CODE,
]);

define('USER_USER_NAME_PREFIX', 'USR');
define('USER_USER_NAME_LENGTH', '10');

define('DEFAULT_USER_PASSWORD', 'schedulePwd');
define('OFFSET_UP', 100);

define('PRICE_PRECISION', 2);
define('PRICE_DECIMAL_CAST', 'decimal:' . PRICE_PRECISION);

/**
 * Entity Types We are going to use in
 * the permission and roles
 * Business Group (1), Organization (2),
 * Branch (3), BranchShop (4)
 */
define('ENTITY_TYPE_ROOT', null);
define('ENTITY_TYPE_BUSINESS_GROUP', 1);
define('ENTITY_TYPE_ORGANIZATION', 2);
define('ENTITY_TYPE_BRANCH', 3);
define('ENTITY_TYPE_BRANCH_SHOP', 4);
define('ENTITY_TYPES', [ENTITY_TYPE_BUSINESS_GROUP, ENTITY_TYPE_ORGANIZATION, ENTITY_TYPE_BRANCH, ENTITY_TYPE_BRANCH_SHOP]);

/** Gender Keys */
define('MALE', 1);
define('FEMALE', 2);
define('GENDERS', [MALE, FEMALE]);

/** Active and Inactive status keys */
define('STATE_ACTIVE', 1);
define('STATE_INACTIVE', 0);
define('STATES', [STATE_ACTIVE, STATE_INACTIVE]);

define('DECIMAL_SUPPORT', true);
define('NO_DECIMAL_SUPPORT', false);

define('USER_STATE_ACTIVE', 1);
define('USER_STATE_INACTIVE', 0);
define('USER_STATES', [USER_STATE_ACTIVE, USER_STATE_INACTIVE]);

// Name of the Super Admin system role (subscription bypass + global scope).
define('SUPER_ADMIN_ROLE_NAME', 'Super Admin');

define('LOGIN_LOCK_TIMEOUT', 600); // Login Lock Timeout in Seconds
define('MAXIMUM_LOGIN_ATTEMPT', 5); // Maximum Login Attempt

define('API_GUARD', 'api'); // The passport api guard
define('API_GUARD_MIDDLEWARE', 'auth:api');

/** Token Expiration Times */
define('ACCESS_TOKEN_EXPIRE_DAYS', 1);   // 1 day
define('REFRESH_TOKEN_EXPIRE_DAYS', 7);  // 7 days

/** Storage Locations */
define('DEFAULT_STORAGE', 'public');
define('USER_PHOTO_LOCATION', '/user-photo');
define('USER_COVER_PHOTO_LOCATION', '/user-cover-photo');

/** PermissionGroup constants */
define('PERMISSION_GROUP_DEFAULT_LEVEL', 1);
define('PERMISSION_GROUP_NON_CHILD_PARENT', 0);
define('PERMISSION_GROUP_SUB_LEVEL', 2);

/** Permission Group Codes */
define('PERMISSION_GROUP_SYSTEM_MANAGEMENT', 'system_management');
define('PERMISSION_GROUP_USER_MANAGEMENT', 'user_management');
define('PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT', 'role_permission_management');
define('PERMISSION_GROUP_PROFILE_MANAGEMENT', 'profile_management');
define('PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT', 'dynamic_value_management');
define('PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT', 'class_schedule_management');
define('PERMISSION_GROUP_MASTER_DATA_MANAGEMENT', 'master_data_management');
define('PERMISSION_GROUP_REPORT_MANAGEMENT', 'report_management');
define('PERMISSION_GROUP_NOTIFICATION_MANAGEMENT', 'notification_management');
define('PERMISSION_GROUP_OFFERING_MANAGEMENT', 'offering_management');

define('DEFAULT_PER_PAGE', 50);
define('PER_PAGE_KEY', 'limit');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DATE_FORMAT_VALIDATION_KEY', 'date_format:' . DATE_FORMAT);

define('TEXT_BLANK', '-');

/** Calendar system preferences */
define('CALENDAR_SYSTEM_GREGORIAN', 1);
define('CALENDAR_SYSTEM_ETHIOPIAN', 2);
define('CONSTANT_ONE', 1);

define('CALENDAR_SYSTEMS', [
    CALENDAR_SYSTEM_GREGORIAN => 'calendarSystemGregorian',
    CALENDAR_SYSTEM_ETHIOPIAN => 'calendarSystemEthiopian',
]);

define('DEFAULT_CALENDAR_SYSTEM', CALENDAR_SYSTEM_ETHIOPIAN);

define('CALENDAR_CACHE_TIMEOUT', 10); // in seconds

/** Boolean Constants */
define('BOOLEAN_TRUE', 1);
define('BOOLEAN_FALSE', 0);

define('ROLE_PERMISSION_UNIQUE_COLUMN', ['role_id', 'permission_id', 'not_deleted']);
define('ADDRESS_PIVOT_UNIQUE_COLUMN', ['woreda_history_id', 'zone_history_id', 'region_history_id']);

define('USER_ROLE_UNIQUE_KEY', 'user_role_unique');
define('USER_ROLE_UNIQUE_COLUMN', ['user_id', 'role_id', 'not_deleted']);

define('USER_PERMISSION_UNIQUE_KEY', 'user_permission_unique');
define('USER_PERMISSION_UNIQUE_COLUMN', ['user_id', 'permission_id', 'not_deleted']);

/**
 * Validation Related
 */
define('NATIONAL_ID_IS_GLOBALLY_MANDATORY', false);
define('MAXIMUM_PHOTO_UPLOAD_SIZE', 2000); // in KB
define('NATIONAL_ID_LENGTH', 16);
define('MIN_NAME_LENGTH', 2);
define('MAX_NAME_LENGTH', 50);
define('MIN_CODE_LENGTH', 3);
define('MAX_SUBSCRIPTION_CODE_LENGTH', 10);
define('MAX_SYMBOL_LENGTH', 5);
define('MAX_LONG_NAME_LENGTH', 100);
define('MIN_DESCRIPTION_LENGTH', 10);
define('MAX_DESCRIPTION_LENGTH', 1000);
define('MAX_EMAIL_LENGTH', 50);
define('MAX_PHONE_LENGTH', 20);
define('MAX_CODE_LENGTH', 50);
define('MAX_FILE_LENGTH', 1024);
define('MAX_REGION_ZONE_WOREDA_CODE_LENGTH', 10);
define('MAX_ICON_SIZE', 2048); // in KB

define('MIN_LEVEL_VALUE', 0);
define('MIN_LEVEL_VALIDATION_KEY', 'min:' . MIN_LEVEL_VALUE);
define('MIN_NAME_VALIDATION_KEY', 'min:' . MIN_NAME_LENGTH);
define('MAX_NAME_VALIDATION_KEY', 'max:' . MAX_NAME_LENGTH);
define('MAX_CODE_LENGTH_VALIDATION_KEY', 'max:' . MAX_CODE_LENGTH);
define('MAX_LATITUDE_LENGTH', 90);
define('MIN_LATITUDE_LENGTH', -90);
define('MIN_LONGITUDE_LENGTH', -100);
define('MAX_LONGITUDE_LENGTH', 100);
define('MAX_TAX_CENTER_CODE_LENGTH', 20);
define('ALLOWED_EMAIL_DOMAINS', [
    '@gmail.com',
    '@yahoo.com',
    '@schedule.et',
    '@schedule.com',
    '@schedule.test',
]);

define('MAX_EXCEL_SIZE', '2048');

/**
 * Scheduling master-data bounds. Every ceiling here mirrors a column width or
 * a CHECK constraint in Docs/Schema/Final Schema.md — change the migration and
 * the constant together, never one alone.
 */
define('MAX_CAMPUS_CODE_LENGTH', 20);   // campuses.code / buildings.code string(20)
define('MAX_ROOM_CODE_LENGTH', 30);     // rooms.code / courses.code / programs.code string(30)
define('MIN_BUILDING_FLOORS', -10);     // basements are honest (signed smallint)
define('MAX_BUILDING_FLOORS', 200);
define('MIN_PROGRAM_DURATION_YEARS', 1); // programs.duration_years CHECK 1..10
define('MAX_PROGRAM_DURATION_YEARS', 10);
define('MIN_SECTION_YEAR_LEVEL', 1);     // sections.year_level CHECK 1..10
define('MAX_SECTION_YEAR_LEVEL', 10);
define('MIN_SEMESTER_TERM', 1);          // semesters.term CHECK 1..3
define('MAX_SEMESTER_TERM', 3);
define('MAX_SECTION_LABEL_LENGTH', 10);  // sections.label string(10)
define('MAX_SECTION_EXPECTED_STUDENTS', 10000);
define('MAX_ROOM_CAPACITY', 10000);      // rooms.capacity / exam_capacity CHECK > 0
define('MIN_COURSE_HOURS', 0.25);        // courses.credit_hours / contact_hours CHECK > 0
define('MAX_COURSE_HOURS', 99.99);       // decimal(4,2) ceiling
define('MAX_SESSIONS_PER_WEEK', 14);     // courses.sessions_per_week
define('MAX_INSTRUCTOR_EMAIL_LENGTH', 150);
define('MAX_ACADEMIC_RANK_LENGTH', 40);
define('MAX_INSTRUCTOR_WEEKLY_HOURS', 999.99); // decimal(5,2) ceiling
define('MAX_EXAM_INVIGILATORS', 20);     // exam_schedules.required_invigilators CHECK >= 1

/** Localized Model attribute suffix */
define('LOCALIZED_SUFFIX', '_localized');
define('LOCALIZED_SUFFIX_WITH_DEFAULT', '__localized');

/** Document Extensions */
define('PDF', 1);
define('EXCEL', 2);
define('CSV', 3);
define('TXT', 4);
define('DOC', 5);
define('DOCX', 6);
define('XLS', 7);
define('XLSX', 8);
define('PPT', 9);
define('PPTX', 10);
define('ZIP', 11);
define('RAR', 12);
define('PDF_EXT', 13);
define('SVG', 14);
define('PNG', 15);
define('JPG', 16);
define('JPEG', 17);
define('GIF', 18);
define('BMP', 19);
define('MP3', 20);
define('MP4', 21);
define('AVI', 22);
define('MKV', 23);
define('MD', 24);
define('EXTENSIONS', [PDF, EXCEL, CSV, TXT, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, RAR, PDF_EXT, SVG, PNG, JPG, JPEG, GIF, BMP, MP3, MP4, AVI, MKV, MD]);
define('DOCUMENT_EXTENSIONS', [
    PDF => 'pdf',
    EXCEL => 'excel',
    CSV => 'csv',
    TXT => 'txt',
    DOC => 'doc',
    DOCX => 'docx',
    XLS => 'xls',
    XLSX => 'xlsx',
    PPT => 'ppt',
    PPTX => 'pptx',
    ZIP => 'zip',
    RAR => 'rar',
    PDF_EXT => 'pdf',
    SVG => 'svg',
    PNG => 'png',
    JPG => 'jpg',
    JPEG => 'jpeg',
    GIF => 'gif',
    BMP => 'bmp',
    MP3 => 'mp3',
    MP4 => 'mp4',
    AVI => 'avi',
    MKV => 'mkv',
    MD => 'md',
]);

define('PROFILE_PHOTO_ALLOWED_EXTENSIONS', [
    DOCUMENT_EXTENSIONS[JPG],
    DOCUMENT_EXTENSIONS[JPEG],
    DOCUMENT_EXTENSIONS[PNG],
]);

define('ICON_ALLOWED_EXTENSIONS', [
    DOCUMENT_EXTENSIONS[SVG],
    DOCUMENT_EXTENSIONS[PNG],
    DOCUMENT_EXTENSIONS[JPG],
    DOCUMENT_EXTENSIONS[JPEG],
]);

/** Currency Constants */
define('CURRENCY_ETB', 'ETB');
define('CURRENCY_USD', 'USD');
define('CURRENCY_EUR', 'EUR');
define('CURRENCY_GBP', 'GBP');
define('CURRENCY_JPY', 'JPY');
define('CURRENCY_CAD', 'CAD');
define('CURRENCY_AUD', 'AUD');
define('CURRENCY_CHF', 'CHF');
define('CURRENCY_CNY', 'CNY');
define('CURRENCY_INR', 'INR');
define('CURRENCY_ZAR', 'ZAR');
define('CURRENCY_KES', 'KES');

/** Notification Types - Shared across all modules */
define('NOTIFICATION_TYPE_SMS', 1);
define('NOTIFICATION_TYPE_EMAIL', 2);
define('NOTIFICATION_TYPE_IN_APP', 3);
define('NOTIFICATION_TYPE_PUSH', 4);

define('NOTIFICATION_TYPES', [
    NOTIFICATION_TYPE_SMS => 'sms',
    NOTIFICATION_TYPE_EMAIL => 'email',
    NOTIFICATION_TYPE_IN_APP => 'in_app',
    NOTIFICATION_TYPE_PUSH => 'push',
]);

/** Notification Statuses - Shared across all modules */
define('NOTIFICATION_STATUS_PENDING', 1);
define('NOTIFICATION_STATUS_SENT', 2);
define('NOTIFICATION_STATUS_FAILED', 3);
define('NOTIFICATION_STATUS_READ', 4);

define('NOTIFICATION_STATUSES', [
    NOTIFICATION_STATUS_PENDING => 'pending',
    NOTIFICATION_STATUS_SENT => 'sent',
    NOTIFICATION_STATUS_FAILED => 'failed',
    NOTIFICATION_STATUS_READ => 'read',
]);

/** Notification template registry constants */
define('NOTIFICATION_TEMPLATE_KEY_USER_REGISTRATION', 'user_registration');
define('NOTIFICATION_TEMPLATE_KEY_OTP', 'otp');

define('NOTIFICATION_TEMPLATE_REQUIRED_DATA_KEY', 'required_data');
define('NOTIFICATION_TEMPLATE_EMAIL_KEY', 'email');
define('NOTIFICATION_TEMPLATE_SMS_KEY', 'sms');
define('ENGLISH_LANG_KEY', 'en');
define('AMHARIC_LANG_KEY', 'am');

/** Notification template names */
define('NOTIFICATION_TEMPLATE_NAME_USER_REGISTRATION_EN', 'user_registration_en');
define('NOTIFICATION_TEMPLATE_NAME_USER_REGISTRATION_AM', 'user_registration_am');
define('NOTIFICATION_TEMPLATE_NAME_OTP_EN', 'otp_verification_en');
define('NOTIFICATION_TEMPLATE_NAME_OTP_AM', 'otp_verification_am');

define('NOTIFICATION_SEND_METHOD_EMAIL', 'email');
define('NOTIFICATION_SEND_METHOD_SMS', 'sms');

/** Queue Names - Shared across all modules */
define('QUEUE_HIGH_PRIORITY', 'high');
define('QUEUE_DEFAULT', 'default');
define('QUEUE_LOW_PRIORITY', 'low');

// Functional queues
define('QUEUE_NOTIFICATIONS', 'notifications');
define('QUEUE_EMAILS', 'emails');
define('QUEUE_SMS', 'sms');

/** Queue Connection Types */
define('QUEUE_CONNECTION_SYNC', 'sync');
define('QUEUE_CONNECTION_DATABASE', 'database');
define('QUEUE_CONNECTION_REDIS', 'redis');

define('RICH_TEXT_MIN_LENGTH', 5);
define('RICH_TEXT_MAX_LENGTH', 3000);
define('GENERAL_FILE_MAX_SIZE', 2048);
define('DROP_DOWN_LIMIT', 8);

define('SEND_RANDOM_PASSWORD', false);
define('PASSWORD_LENGTH', 8);

// Rate limiting constants
define('LOGIN_RATE_LIMIT_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_DECAY', 900);
define('OTP_LENGTH', 6);
DEFINE('OTP_EXPIRATION_TIME', 5);
/** generateCode() — Format constants */
define('CODE_FORMAT_SNAKE', 'snake');
define('CODE_FORMAT_UPPER_SNAKE', 'upper_snake');
define('CODE_FORMAT_SLUG', 'slug');
define('CODE_FORMAT_UPPER_SLUG', 'upper_slug');
define('CODE_FORMAT_UPPER', 'upper');
define('CODE_FORMAT_ABBR', 'abbr');
define('CODE_FORMAT_PREFIX', 'prefix');

/** generateCode() — Option key constants */
define('CODE_OPT_PREFIX', 'prefix');
define('CODE_OPT_SEPARATOR', 'separator');
define('CODE_OPT_COUNTER', 'counter');
define('CODE_OPT_PAD', 'pad');
define('CODE_OPT_UNIQUE', 'unique');
define('CODE_OPT_MODEL', 'model');
define('CODE_OPT_FIELD', 'field');

define('DEFAULT_PERMISSION_KEY', 'permissions');

define('USER_BACKUP_CODE_NEW', 1);
define('USER_BACKUP_CODE_USED', 2);
define('USER_BACKUP_CODE_DISCARDED', 3);
define('NUMBER_OF_BACKUP_CODES', 6);
define('BACKUP_CODE_LENGTH', 6);

// for all
define('ACTION_ACTIVATE_FOR_ALL', 'activate');
define('ACTION_DEACTIVATE_FOR_ALL', 'deactivate');
define('ACTION_DELETE_FOR_ALL', 'delete');
define('ACTION_CHANGE_STATUS_FOR_ALL', 'change_status');
define('ACTION_DECIMAL_SUPPORT', 'decimal_support');
define('ACTION_NO_DECIMAL_SUPPORT', 'no_decimal_support');
define('FOR_ALL_ACTIONS', [
    ACTION_ACTIVATE_FOR_ALL,
    ACTION_DEACTIVATE_FOR_ALL,
    ACTION_DELETE_FOR_ALL,
    ACTION_CHANGE_STATUS_FOR_ALL,
    ACTION_DECIMAL_SUPPORT,
    ACTION_NO_DECIMAL_SUPPORT,
]);

// Constants
define('ACTION_ACTIVATE', 'activate');
define('ACTION_DEACTIVATE', 'deactivate');
define('ACTION_DELETE', 'delete');
define('ACTIONS', [
    ACTION_ACTIVATE,
    ACTION_DEACTIVATE,
    ACTION_DELETE,
]);

// Entity Type Actions
define('ACTION_ACTIVATE_ENTITY_TYPE', 'activate');
define('ACTION_DEACTIVATE_ENTITY_TYPE', 'deactivate');
define('ACTION_DELETE_ENTITY_TYPE', 'delete');
define('ENTITY_TYPE_ACTIONS', [
    ACTION_ACTIVATE_ENTITY_TYPE,
    ACTION_DEACTIVATE_ENTITY_TYPE,
    ACTION_DELETE_ENTITY_TYPE,
]);

// Admin unit type actions
define('ACTION_ACTIVATE_ADMIN_UNIT', 'activate');
define('ACTION_DEACTIVATE_ADMIN_UNIT', 'deactivate');
define('ACTION_DELETE_ADMIN_UNIT', 'delete');
define('ADMIN_UNIT_ACTIONS', [
    ACTION_ACTIVATE_ADMIN_UNIT,
    ACTION_DEACTIVATE_ADMIN_UNIT,
    ACTION_DELETE_ADMIN_UNIT,
]);

// Entity Actions
define('ACTION_ACTIVATE_ENTITY', 'activate');
define('ACTION_DEACTIVATE_ENTITY', 'deactivate');
define('ACTION_DELETE_ENTITY', 'delete');
define('ENTITY_ACTIONS', [
    ACTION_ACTIVATE_ENTITY,
    ACTION_DEACTIVATE_ENTITY,
    ACTION_DELETE_ENTITY,
]);

// Measurement Category Actions
define('ACTION_ACTIVATE_MEASUREMENT_CATEGORY', 'activate');
define('ACTION_DEACTIVATE_MEASUREMENT_CATEGORY', 'deactivate');
define('ACTION_DELETE_MEASUREMENT_CATEGORY', 'delete');
define('MEASUREMENT_CATEGORY_ACTIONS', [
    ACTION_ACTIVATE_MEASUREMENT_CATEGORY,
    ACTION_DEACTIVATE_MEASUREMENT_CATEGORY,
    ACTION_DELETE_MEASUREMENT_CATEGORY,
]);

// Billing Cycle Actions
define('ACTION_ACTIVATE_BILLING_CYCLE', 'activate');
define('ACTION_DEACTIVATE_BILLING_CYCLE', 'deactivate');
define('ACTION_DELETE_BILLING_CYCLE', 'delete');
define('BILLING_CYCLE_ACTIONS', [
    ACTION_ACTIVATE_BILLING_CYCLE,
    ACTION_DEACTIVATE_BILLING_CYCLE,
    ACTION_DELETE_BILLING_CYCLE,
]);

// Pricing Plan Actions
define('ACTION_ACTIVATE_PRICING_PLAN', 'activate');
define('ACTION_DEACTIVATE_PRICING_PLAN', 'deactivate');
define('ACTION_DELETE_PRICING_PLAN', 'delete');
define('PRICING_PLAN_ACTIONS', [
    ACTION_ACTIVATE_PRICING_PLAN,
    ACTION_DEACTIVATE_PRICING_PLAN,
    ACTION_DELETE_PRICING_PLAN,
]);

// Subscription discount type
define('ACTION_ACTIVATE_SUBSCRIPTION_DISCOUNT_TYPE', 'activate');
define('ACTION_DEACTIVATE_SUBSCRIPTION_DISCOUNT_TYPE', 'deactivate');
define('ACTION_DELETE_SUBSCRIPTION_DISCOUNT_TYPE', 'delete');
define('SUBSCRIPTION_DISCOUNT_TYPE_ACTIONS', [
    ACTION_ACTIVATE_SUBSCRIPTION_DISCOUNT_TYPE,
    ACTION_DEACTIVATE_SUBSCRIPTION_DISCOUNT_TYPE,
    ACTION_DELETE_SUBSCRIPTION_DISCOUNT_TYPE,
]);

// Subscription discount
define('ACTION_ACTIVATE_SUBSCRIPTION_DISCOUNT', 'activate');
define('ACTION_DEACTIVATE_SUBSCRIPTION_DISCOUNT', 'deactivate');
define('ACTION_DELETE_SUBSCRIPTION_DISCOUNT', 'delete');
define('SUBSCRIPTION_DISCOUNT_ACTIONS', [
    ACTION_ACTIVATE_SUBSCRIPTION_DISCOUNT,
    ACTION_DEACTIVATE_SUBSCRIPTION_DISCOUNT,
    ACTION_DELETE_SUBSCRIPTION_DISCOUNT,
]);

// Entitlement override
define('ACTION_ACTIVATE_ENTITLEMENT_OVERRIDE', 'activate');
define('ACTION_DEACTIVATE_ENTITLEMENT_OVERRIDE', 'deactivate');
define('ACTION_DELETE_ENTITLEMENT_OVERRIDE', 'delete');
define('ENTITLEMENT_OVERRIDE_ACTIONS', [
    ACTION_ACTIVATE_ENTITLEMENT_OVERRIDE,
    ACTION_DEACTIVATE_ENTITLEMENT_OVERRIDE,
    ACTION_DELETE_ENTITLEMENT_OVERRIDE,
]);

define('MEDIA_TYPE_IMAGE', 'image');
define('MEDIA_TYPE_VIDEO', 'video');
define('MEDIA_TYPE_DOCUMENT', 'document');
define('MEDIA_TYPE_UNKNOWN', 'unknown');
define('MEDIA_TYPES', [
    MEDIA_TYPE_IMAGE => ['image/'],
    MEDIA_TYPE_VIDEO => ['video/'],
    MEDIA_TYPE_DOCUMENT => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
]);

// Document type actions
define('ACTION_DELETE_DOCUMENT_TYPE', 'delete');
define('ACTION_ACTIVATE_DOCUMENT_TYPE', 'activate');
define('ACTION_DEACTIVATE_DOCUMENT_TYPE', 'deactivate');
define('ACTION_CHANGE_STATUS_DOCUMENT_TYPE', 'change_status');
define('DOCUMENT_TYPE_ACTIONS', [
    ACTION_DELETE_DOCUMENT_TYPE,
    ACTION_ACTIVATE_DOCUMENT_TYPE,
    ACTION_DEACTIVATE_DOCUMENT_TYPE,
    ACTION_CHANGE_STATUS_DOCUMENT_TYPE,
]);

// Types
define('TYPE_COUNTRY_ADMIN_LEVEL', 1);
define('TYPE_REGION_ADMIN_LEVEL', 2);
define('TYPE_ZONE_ADMIN_LEVEL', 3);
define('TYPE_WOREDA_ADMIN_LEVEL', 4);
define('TYPE_CITY_ADMIN_LEVEL', 5);
define('TYPE_KEBELE_ADMIN_LEVEL', 6);
define('TYPE_VILLAGE_ADMIN_LEVEL', 7);

define('TYPE_RETAIL_POS', 1);
define('TYPE_HOSPITALITY_POS', 2);
define('TYPE_MIXED_POS', 3);

define('TYPE_BUY_X_GET_Y', 1);
define('TYPE_SPEND_THRESHOLD_GIFT', 2);
define('TYPE_CATEGORY_DISCOUNT', 3);
define('TYPE_PRODUCT_DISCOUNT', 4);
define('TYPE_INVOICE_DISCOUNT', 5);
define('TYPE_PROMO_CODE', 6);

// Condition Types
define('TYPE_MIN_QUANTITY', 1);
define('TYPE_MIN_AMOUNT', 2);
define('TYPE_PRODUCT', 3);
define('TYPE_CATEGORY', 4);
define('TYPE_BRAND', 5);

// Reward Types
define('TYPE_FREE_PRODUCT', 1);
define('TYPE_DISCOUNT_PERCENT', 2);
define('TYPE_DISCOUNT_AMOUNT', 3);

// Operation Types
define('TYPE_IN', 1);
define('TYPE_OUT', 2);
define('TYPE_TRANSFER_IN', 3);
define('TYPE_TRANSFER_OUT', 4);
define('TYPE_ADJUSTMENT', 5);

// Reason Types
define('TYPE_REASON_OUT', 1);
define('TYPE_REASON_ADJUSTMENT', 2);
define('TYPE_REASON_RECALL_AND_RETURN', 3);

// Product Registration Types
define('TYPE_SALE', 1);
define('TYPE_PURCHASE', 2);
define('TYPE_BOTH_SALES_AND_PURCHASE', 3);

// Dining Table Status Types
define('TYPE_AVAILABLE', 1);
define('TYPE_OCCUPIED', 2);
define('TYPE_RESERVED', 3);
define('TYPE_CLEANING', 4);
define('TYPE_OUT_OF_SERVICE', 5);

// Order Types
define('TYPE_DINE_IN', 1);
define('TYPE_TAKEAWAY', 2);
define('TYPE_DELIVERY', 3);

// Order Status Types
define('TYPE_DRAFT', 1);
define('TYPE_CONFIRMED', 2);
define('TYPE_IN_PROGRESS', 3);
define('TYPE_SERVED', 4);
define('TYPE_COMPLETED', 5);
define('TYPE_CANCELLED', 6);

// Order Item Status Types
define('TYPE_PENDING', 1);
define('TYPE_PARTIAL', 2);
define('TYPE_SENT', 3);
define('TYPE_ITEM_SERVED', 4);
define('TYPE_ITEM_CANCELLED', 5);

// StockOut Types
define('TYPE_STOCKOUT_SALE', 1);
define('TYPE_STOCKOUT_DAMAGE', 2);
define('TYPE_STOCKOUT_EXPIRED', 3);
define('TYPE_STOCKOUT_INTERNAL_USE', 4);

// Adjustment Types
define('TYPE_ADJUSTMENT_DAMAGE', 1);
define('TYPE_ADJUSTMENT_LOSS', 2);
define('TYPE_ADJUSTMENT_COUNT_DIFFERENCE', 3);

// Legality Types
define('TYPE_LEGALITY_PRIVATE', 1);
define('TYPE_LEGALITY_GOVERNMENT', 2);

// Costing Rule Types
define('TYPE_COSTING_RULE_LIFO', 1);
define('TYPE_COSTING_RULE_WAC', 2);

// Status Types
define('TYPE_STATUS_PENDING', 1);
define('TYPE_STATUS_SUBMITTED', 2);
define('TYPE_STATUS_LOCKED', 3);
define('TYPE_STATUS_APPROVED', 4);
define('TYPE_STATUS_REJECTED', 5);
define('TYPE_STATUS_EXPIRED', 6);

// warehouse types
define('TYPE_WAREHOUSE_CHEMICAL', 1);
define('TYPE_WAREHOUSE_SPARE_PART', 2);
define('TYPE_WAREHOUSE_PHARMACEUTICAL', 3);
define('TYPE_WAREHOUSE_CENTRAL', 4);
define('TYPE_WAREHOUSE_STORE_BACKROOM', 5);
define('TYPE_WAREHOUSE_KITCHEN_STORE', 6);
define('TYPE_WAREHOUSE_COLD_STORAGE', 7);
define('TYPE_WAREHOUSE_WASTAGE_STORAGE', 8);
define('TYPE_WAREHOUSE_TRANSIT_STORAGE', 9);

// ToDo: remove this variables if they are columns from the Models
define('SUBSCRIPTION_END_AT', 'end_at');
define('SUBSCRIPTION_END_FROM', 'end_from');
define('SUBSCRIPTION_END_TO', 'end_to');
define('SUBSCRIPTION_START_AT', 'start_at');
define('SUBSCRIPTION_START_FROM', 'start_from');
define('SUBSCRIPTION_START_TO', 'start_to');
define('SUBSCRIPTION_PROGRESS', 'progress');
define('SUBSCRIPTION_PERCENT', 'percent');
define('SUBSCRIPTION_REMAINING_DAYS', 'remaining_days');
define('SUBSCRIPTION_ELAPSED_DAYS', 'elapsed_days');
define('SUBSCRIPTION_TOTAL_DAYS', 'total_days');
define('SUBSCRIPTION_MODULE', 'module');

// Location Category Types
define('TYPE_PHYSICAL_LOCATION_CATEGORY', 1);
define('TYPE_LOGICAL_LOCATION_CATEGORY', 2);
define('TYPE_VIRTUAL_LOCATION_CATEGORY', 3);
define('TYPE_TRANSIT_LOCATION_CATEGORY', 4);

// UI Component Types
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_INPUT', 'input');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_NUMBER', 'number');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_TOGGLE', 'toggle');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_SELECT', 'select');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_RADIO', 'radio');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_TEXTAREA', 'textarea');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPE_DATE', 'date');
define('LOCATION_PROPERTY_UI_COMPONENT_TYPES', [
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_INPUT,
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_NUMBER,
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_TOGGLE,
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_SELECT,
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_RADIO,
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_TEXTAREA,
    LOCATION_PROPERTY_UI_COMPONENT_TYPE_DATE,
]);

// Location Type Property Data Types
define('LOCATION_TYPE_PROPERTY_DATA_TYPE_TEXT', 'text');
define('LOCATION_TYPE_PROPERTY_DATA_TYPE_NUMBER', 'number');
define('LOCATION_TYPE_PROPERTY_DATA_TYPE_BOOLEAN', 'boolean');
define('LOCATION_TYPE_PROPERTY_DATA_TYPE_DATE', 'date');
define('LOCATION_TYPE_PROPERTY_DATA_TYPE_SELECT', 'select');
define('LOCATION_TYPE_PROPERTY_DATA_TYPE_JSON', 'json');
define('LOCATION_TYPE_PROPERTY_DATA_TYPES', [
    LOCATION_TYPE_PROPERTY_DATA_TYPE_TEXT,
    LOCATION_TYPE_PROPERTY_DATA_TYPE_NUMBER,
    LOCATION_TYPE_PROPERTY_DATA_TYPE_BOOLEAN,
    LOCATION_TYPE_PROPERTY_DATA_TYPE_DATE,
    LOCATION_TYPE_PROPERTY_DATA_TYPE_SELECT,
    LOCATION_TYPE_PROPERTY_DATA_TYPE_JSON,
]);

define('PHONE_CODE_REGEX', '/^\+[1-9][0-9]{0,2}$/');
define('MAX_PHONE_CODE', 5);
define('SYMBOL_REGEX', '/^[a-zA-Z0-9°\/\-\.\s²³]+$/u');
define('ADMIN_LEVEL_DEFAULT_LEVEL', 1);

define('MIN_HOLDABLE_PERCENT', 0);
define('MIN_TAX_RATE_PERCENT', 0);
define('MAX_HOLDABLE_PERCENT', 100);
define('MAX_TAX_RATE_PERCENT', 100);
define('MIN_TAX_HOLD_RATE_PERCENT', 0);
define('MAX_TAX_HOLD_RATE_PERCENT', 100);

define('DOCUMENT_TYPE_DEFAULT_DIGIT', 4);
define('DOCUMENT_TYPE_MAX_CODE', 50);
define('DOCUMENT_TYPE_MIN_DIGIT', 1);
define('DOCUMENT_TYPE_MAX_DIGIT', 10);
define('CUSTOMER_MIN_CREDIT_LIMIT', 0);
define('SUPPLIER_MIN_LEAD_TIME_DAYS', 0);

define('MAX_TIN_NUMBER', 50);
define('MIN_PHONE_LENGTH', 10);

define('MAX_ICON_SVG_BYTES', 64 * 1024);
define('MAX_ICON_IMAGE_BYTES', 1024 * 1024);
define('MAX_ICON_URL_LENGTH', 2048);
define('MAX_ICON_FILENAME_LENGTH', 255);
define('MAX_ICON_TAG_LENGTH', 64);
define('MAX_ICON_TAGS_COUNT', 30);
define('MAX_ICON_IMPORT_BATCH', 200);

define('ICON_IMAGE_MIME_EXTENSIONS', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp']);

define('ALLOWED_ICON_IMAGE_MIME', array_keys(ICON_IMAGE_MIME_EXTENSIONS));
define('ICON_IMAGE_UPLOAD_EXTENSIONS', array_values(ICON_IMAGE_MIME_EXTENSIONS));
define('ICON_IMAGE_UPLOAD_MIMES_RULE', 'mimes:' . implode(',', ICON_IMAGE_UPLOAD_EXTENSIONS));
define('ICON_IMAGE_UPLOAD_MAX_RULE', 'max:' . (int) ceil(MAX_ICON_IMAGE_BYTES / 1024));

define('ICON_SOURCE_TYPES', ['svg', 'image', 'url']);
define('ICON_SVG_MIME_TYPE', 'image/svg+xml');
define('ICON_STORAGE_DIR', 'icons');
define('ICON_SORTABLE_FIELDS', ['name', 'created_at']);
define('ICON_DEFAULT_SORT_FIELD', 'name');
define('ICON_DEFAULT_SORT_DIRECTION', 'asc');
define('ICON_SORT_DIRECTIONS', ['asc', 'desc']);

// workflow actions
define('ACTION_DELETE_WORKFLOW', 'delete');
define('ACTION_ACTIVATE_WORKFLOW', 'activate');
define('ACTION_DEACTIVATE_WORKFLOW', 'deactivate');
define('ACTION_CHANGE_STATUS_WORKFLOW', 'change_status');
define('WORKFLOW_ACTIONS', [
    ACTION_DELETE_WORKFLOW,
    ACTION_ACTIVATE_WORKFLOW,
    ACTION_DEACTIVATE_WORKFLOW,
    ACTION_CHANGE_STATUS_WORKFLOW,
]);
define('DOCUMENT_TYPE_ATTACHMENTS_TYPES', ['jpeg', 'png', 'jpg', 'svg', 'pdf', 'doc', 'docx']);

// Custom Field Engine
define('CUSTOM_FIELD_TYPE_STRING', 'string');
define('CUSTOM_FIELD_TYPE_NUMBER', 'number');
define('CUSTOM_FIELD_TYPE_BOOLEAN', 'boolean');
define('CUSTOM_FIELD_TYPE_DATE', 'date');
define('CUSTOM_FIELD_TYPE_SELECT', 'select');
define('CUSTOM_FIELD_TYPE_MULTISELECT', 'multiselect');
define('CUSTOM_FIELD_TYPE_JSON', 'json');
define('CUSTOM_FIELD_TYPE_FILE', 'file');
define('CUSTOM_FIELD_DATA_TYPES', [
    CUSTOM_FIELD_TYPE_STRING,
    CUSTOM_FIELD_TYPE_NUMBER,
    CUSTOM_FIELD_TYPE_BOOLEAN,
    CUSTOM_FIELD_TYPE_DATE,
    CUSTOM_FIELD_TYPE_SELECT,
    CUSTOM_FIELD_TYPE_MULTISELECT,
    CUSTOM_FIELD_TYPE_JSON,
    CUSTOM_FIELD_TYPE_FILE,
]);

define('CUSTOM_FIELD_UI_INPUT', 'input');
define('CUSTOM_FIELD_UI_TEXTAREA', 'textarea');
define('CUSTOM_FIELD_UI_SELECT', 'select');
define('CUSTOM_FIELD_UI_MULTISELECT', 'multiselect');
define('CUSTOM_FIELD_UI_TOGGLE', 'toggle');
define('CUSTOM_FIELD_UI_CHECKBOX', 'checkbox');
define('CUSTOM_FIELD_UI_RADIO', 'radio');
define('CUSTOM_FIELD_UI_DATE', 'date');
define('CUSTOM_FIELD_UI_FILE', 'file');
define('CUSTOM_FIELD_UI_COMPONENTS', [
    CUSTOM_FIELD_UI_INPUT,
    CUSTOM_FIELD_UI_TEXTAREA,
    CUSTOM_FIELD_UI_SELECT,
    CUSTOM_FIELD_UI_MULTISELECT,
    CUSTOM_FIELD_UI_TOGGLE,
    CUSTOM_FIELD_UI_CHECKBOX,
    CUSTOM_FIELD_UI_RADIO,
    CUSTOM_FIELD_UI_DATE,
    CUSTOM_FIELD_UI_FILE,
]);

define('OPTION_SOURCE_STATIC', 'static');
define('OPTION_SOURCE_MODEL', 'model');
define('OPTION_SOURCE_API', 'api');
define('OPTION_SOURCE_TYPES', [
    OPTION_SOURCE_STATIC,
    OPTION_SOURCE_MODEL,
    OPTION_SOURCE_API,
]);
define('OPTION_SOURCE_MODEL_MAX_ROWS', 500);
define('CUSTOM_FIELD_FILES_LOCATION', 'custom-fields');
define('CUSTOM_FIELD_FILE_MAX_KB', 10240);

define('CUSTOM_FIELD_SORTABLE_FIELDS', ['sort_order', 'name_key', 'created_at']);

// Column-listing purpose for the builder: "@" format tokens vs option label/value pickers.
define('CUSTOM_FIELD_COLUMN_PURPOSE_FORMAT', 'format');
define('CUSTOM_FIELD_COLUMN_PURPOSE_OPTIONS', 'options');
define('CUSTOM_FIELD_COLUMN_PURPOSES', [
    CUSTOM_FIELD_COLUMN_PURPOSE_FORMAT,
    CUSTOM_FIELD_COLUMN_PURPOSE_OPTIONS,
]);

// Columns probed (in order) to auto-detect a record's display label.
define('CUSTOM_FIELD_LABEL_COLUMNS', ['name', 'title', 'label', 'full_name', 'display_name', 'code']);

// Billing cycle periods
define('WEEKLY', 7);
define('MONTHLY', 30);
define('QUARTERLY', 90);
define('SEMI_ANUAL', 180);
define('ANUAL', 365);

// Pricing categories
define('PLAN', 'PLAN');
define('MODULE', 'MODULE');
define('FEATURE', 'FEATURE');
define('ADDON', 'ADDON');
define('BUNDLE', 'BUNDLE');
define('USAGE', 'USAGE');
define('INITIAL_VERSION', 1);
// ERP Subscription related constants
define('MIN_PRICE_PER_USER', 0);
define('MIN_PRICE_PER_UNIT', 0);
define('MAX_PRICE_PER_USER', 1000000000);
define('MAX_PRICE_PER_UNIT', 1000000000);
define('MIN_USER_INTERVAL_FOR_EACH_SUBSCRIPTION', 1);
define('MAX_USER_INTERVAL_FOR_EACH_SUBSCRIPTION', 100);
define('MIN_USAGE_INTERVAL_FOR_EACH_USAGE_PRICING_PLAN', 0);
define('MAX_USAGE_INTERVAL_FOR_EACH_USAGE_PRICING_PLAN', 10000);
define('INITIIAL_PRICE_PLAN_VERSION', 1);
define('PRICE_PLAN_VERSION_INCREMENT', 0.1);
define('MINIMUM_USERS_FOR_EACH_PRICE_PLAN', 1);
define('MAXIMUM_USERS_FOR_EACH_PRICE_PLAN', 100);
define('PROMO_CODE', 'PROMO_CODE');

define('OUTLET_DEFAULT_TIP_PERCENT', 10.00);
define('FIRST', 1);
