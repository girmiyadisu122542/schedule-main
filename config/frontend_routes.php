<?php

use App\Constants\FrontendPaths;

return [
        'routes' => [
                FrontendPaths::HOME => [],
                FrontendPaths::LOGIN => [],
                FrontendPaths::FORGOT_PASSWORD => [],
                FrontendPaths::VERIFY_ACCOUNT => [],
                FrontendPaths::DASHBOARD => [PERMISSION_SEE_DASHBOARD],

                /** User Related Routes */
                FrontendPaths::MANAGE_USERS => [PERMISSION_CREATE_USER, PERMISSION_SEE_USER, PERMISSION_UPDATE_USER],

                /** Permission Related Routes */
                FrontendPaths::PERMISSION_GROUP => [PERMISSION_CREATE_PERMISSION_GROUP],
                FrontendPaths::MANAGE_PERMISSIONS => [PERMISSION_SEE_PERMISSION, PERMISSION_CREATE_PERMISSION, PERMISSION_UPDATE_PERMISSION, PERMISSION_DELETE_PERMISSION, PERMISSION_CHANGE_PERMISSION_STATUS],

                /** Role Related Routes */
                FrontendPaths::MANAGE_ROLES => [PERMISSION_SEE_ROLE],
                FrontendPaths::CREATE_ROLE => [PERMISSION_CREATE_ROLE],
                FrontendPaths::CREATE_ROLE_PERMISSION => [PERMISSION_CREATE_ROLE, PERMISSION_ADD_ROLE_PERMISSION],
                FrontendPaths::ROLE_PERMISSIONS => [PERMISSION_SEE_ROLE, PERMISSION_ADD_ROLE_PERMISSION],
                FrontendPaths::ASSIGN_ROLE => [PERMISSION_ASSIGN_ROLE_TO_USER],

                /** Lookup (dynamic value) Related Routes */
                FrontendPaths::MANAGE_DYNAMIC_VALUES => [PERMISSION_SEE_DYNAMIC_VALUE, PERMISSION_CREATE_DYNAMIC_VALUE, PERMISSION_UPDATE_DYNAMIC_VALUE, PERMISSION_DELETE_DYNAMIC_VALUE, PERMISSION_CHANGE_DYNAMIC_VALUE_STATUS, PERMISSION_CHANGE_DYNAMIC_VALUE_STATE],

                /** Profile Related Routes */
                FrontendPaths::MANAGE_PROFILE => [PERMISSION_SEE_PROFILE, PERMISSION_UPDATE_PROFILE],
        ],

        'auth_redirect' => FrontendPaths::DASHBOARD,
        'unauth_redirect' => FrontendPaths::HOME,
];
