<?php

use App\Models\Common\Lookup\LookupTransition;
use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use App\Models\Otp\Otp;
use App\Models\Permission\Permission;
use App\Models\Permission\PermissionGroup;
use App\Models\Role\Role;
use App\Models\Role\RolePermission;
use App\Models\Role\UserPermissionOverride;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use App\Models\User\LoginAttempt;
use App\Models\User\User2FA;
use App\Models\User\UserBackupCode;
use App\Models\User\UserDetail;
use App\Models\User\UserLog;
use App\Models\User\UserOTPCode;

/*
 * Model registry.
 */
return [
    // User Management Models
    MODEL_USER => [
        'class' => User::class,
        'name' => 'user',
        'support_custom_field' => false,
    ],
    MODEL_USER_DETAIL => [
        'class' => UserDetail::class,
        'name' => 'userDetail',
        'support_custom_field' => false,
    ],
    MODEL_USER_LOG => [
        'class' => UserLog::class,
        'name' => 'userLog',
        'support_custom_field' => false,
    ],
    MODEL_LOGIN_ATTEMPT => [
        'class' => LoginAttempt::class,
        'name' => 'loginAttempt',
        'support_custom_field' => false,
    ],
    MODEL_USER2_F_A => [
        'class' => User2FA::class,
        'name' => 'user2FA',
        'support_custom_field' => false,
    ],
    MODEL_USER_BACKUP_CODE => [
        'class' => UserBackupCode::class,
        'name' => 'userBackupCode',
        'support_custom_field' => false,
    ],
    MODEL_USER_O_T_P_CODE => [
        'class' => UserOTPCode::class,
        'name' => 'userOTPCode',
        'support_custom_field' => false,
    ],
    MODEL_OTP => [
        'class' => Otp::class,
        'name' => 'otp',
        'support_custom_field' => false,
    ],

    // Role & Permission Models
    MODEL_ROLE => [
        'class' => Role::class,
        'name' => 'role',
        'support_custom_field' => false,
    ],
    MODEL_PERMISSION => [
        'class' => Permission::class,
        'name' => 'permission',
        'support_custom_field' => false,
    ],
    MODEL_PERMISSION_GROUP => [
        'class' => PermissionGroup::class,
        'name' => 'permissionGroup',
        'support_custom_field' => false,
    ],
    MODEL_ROLE_PERMISSION => [
        'class' => RolePermission::class,
        'name' => 'rolePermission',
        'support_custom_field' => false,
    ],
    MODEL_USER_ROLE_BINDING => [
        'class' => UserRoleBinding::class,
        'name' => 'userRoleBinding',
        'support_custom_field' => false,
    ],
    MODEL_USER_PERMISSION_OVERRIDE => [
        'class' => UserPermissionOverride::class,
        'name' => 'userPermissionOverride',
        'support_custom_field' => false,
    ],

    // Lookup Models (config catalogues)
    MODEL_LOOKUP_TYPE => [
        'class' => LookupType::class,
        'name' => 'lookupType',
        'support_custom_field' => false,
    ],
    MODEL_LOOKUP_VALUE => [
        'class' => LookupValue::class,
        'name' => 'lookupValue',
        'support_custom_field' => false,
    ],
    MODEL_LOOKUP_TRANSITION => [
        'class' => LookupTransition::class,
        'name' => 'lookupTransition',
        'support_custom_field' => false,
    ],
];
