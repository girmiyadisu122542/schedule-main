<?php

return [
    // User Status Lookup
    [
        'name' => 'userStatus',
        'code' => USER_STATUS,
        'description' => 'userStatusDesc',
        'applies_to_model' => [MODEL_USER],
        'is_system' => true,
        'values' => [
            ['name' => 'active', 'code' => USER_STATUS_ACTIVE, 'order' => 1, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => 'inactive', 'code' => USER_STATUS_INACTIVE, 'order' => 2, 'color' => '#6B7280', 'icon' => 'x-circle'],
            ['name' => 'pending', 'code' => USER_STATUS_PENDING_VERIFICATION, 'order' => 5, 'color' => '#F59E0B', 'icon' => 'clock'],
        ],
        'transitions' => [
            ['from' => USER_STATUS_PENDING_VERIFICATION, 'to' => USER_STATUS_ACTIVE],
            ['from' => USER_STATUS_ACTIVE, 'to' => USER_STATUS_INACTIVE],
            ['from' => USER_STATUS_INACTIVE, 'to' => USER_STATUS_ACTIVE],
        ],
    ],

    // Generic Status (can be used for multiple models)
    [
        'name' => 'genericStatus',
        'code' => GENERIC_STATUS,
        'description' => 'genericStatusDesc',
        'applies_to_model' => [],
        'is_system' => false,
        'values' => [
            ['name' => 'active', 'code' => USER_STATUS_ACTIVE, 'order' => 1, 'color' => '#10B981'],
            ['name' => 'inactive', 'code' => USER_STATUS_INACTIVE, 'order' => 2, 'color' => '#6B7280'],
        ],
        'transitions' => [
            ['from' => USER_STATUS_ACTIVE, 'to' => USER_STATUS_INACTIVE],
            ['from' => USER_STATUS_INACTIVE, 'to' => USER_STATUS_ACTIVE],
        ],
    ],

    // Lookup value status (drives the status-based query engine)
    [
        'name' => 'lookupValueStatus',
        'code' => LOOKUP_VALUE_STATUS,
        'description' => 'lookupValueStatusDesc',
        'applies_to_model' => [
            MODEL_LOOKUP_VALUE,
        ],
        'is_system' => true,
        'values' => [
            ['name' => 'pending', 'code' => LOOKUP_VALUE_STATUS_PENDING, 'order' => 1, 'color' => '#F59E0B', 'icon' => 'clock'],
            ['name' => 'acceptForAll', 'code' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL, 'order' => 2, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => 'acceptForThis', 'code' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS, 'order' => 3, 'color' => '#3B82F6', 'icon' => 'check-circle'],
            ['name' => 'reject', 'code' => LOOKUP_VALUE_STATUS_REJECT, 'order' => 4, 'color' => '#EF4444', 'icon' => 'x-circle'],
        ],
        'transitions' => [
            ['from' => LOOKUP_VALUE_STATUS_PENDING, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL],
            ['from' => LOOKUP_VALUE_STATUS_PENDING, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS],
            ['from' => LOOKUP_VALUE_STATUS_PENDING, 'to' => LOOKUP_VALUE_STATUS_REJECT],

            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL],
            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS],

            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS, 'to' => LOOKUP_VALUE_STATUS_REJECT],
            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL, 'to' => LOOKUP_VALUE_STATUS_REJECT],

            ['from' => LOOKUP_VALUE_STATUS_REJECT, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL],
            ['from' => LOOKUP_VALUE_STATUS_REJECT, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS],
        ],
    ],
];
