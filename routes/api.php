<?php

use App\Http\Controllers\Auth\DeviceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Constant\ConstantsIndexController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\Lang\LanguageController;
use App\Http\Controllers\Lookup\LookupTransitionController;
use App\Http\Controllers\Lookup\LookupTypeController;
use App\Http\Controllers\Lookup\LookupValueController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Permission\PermissionGroupController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\Schedule\ClassScheduleController;
use App\Http\Controllers\User\AllowedRoutesController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/front-language', [LanguageController::class, 'getTranslations']);
Route::get('/user/allowed-routes', [AllowedRoutesController::class, 'index']);

Route::post('/forget-password', [PasswordController::class, 'forgetPassword']);
Route::post('/reset-password', [PasswordController::class, 'resetPassword']);
Route::post('/verify-otp', [PasswordController::class, 'verifyOtp']);
Route::post('/resend-otp', [PasswordController::class, 'resendOtp']);

Route::post('/2fa/send-otp', [TwoFactorController::class, 'sendLoginOtp']);
Route::post('/2fa/verify-otp', [TwoFactorController::class, 'verifyLoginOtp']);
Route::post('/2fa/verify-backup', [TwoFactorController::class, 'verifyBackupCode']);

Route::post('/login', [LoginController::class, 'login'])
    ->middleware('rate_limit:' . LOGIN_RATE_LIMIT_ATTEMPTS . ',' . LOGIN_RATE_LIMIT_DECAY);

Route::middleware(API_GUARD_MIDDLEWARE)
    ->group(function () {
        // Do Not touch this route
        // secured files urls routes
        Route::get('/files/secure/{disk}/{path}', [FileController::class, 'serve'])
            ->where('path', '.*')
            ->middleware('signed')
            ->name(SECURE_FILE_ROUTE);

        // Account routes
        Route::prefix('/account')
            ->group(function () {
                Route::post('/logout', [LoginController::class, 'logout']);
                Route::get('/me', [UserController::class, 'authUser']);
                Route::post('/profile', [UserController::class, 'updateProfile']);
                Route::post('/change-password', [PasswordController::class, 'changePassword']);

                Route::get('/sessions', [DeviceController::class, 'index']);
                Route::delete('/sessions/{id}', [DeviceController::class, 'terminateSession']);
                Route::delete('/sessions', [DeviceController::class, 'terminateAllSessions']);

                Route::prefix('/2fa')
                    ->group(function () {
                        Route::get('/status', [TwoFactorController::class, 'userMFAStatus']);
                        Route::post('/enable-mfa', [TwoFactorController::class, 'enableMFA']);
                        Route::post('/disable', [TwoFactorController::class, 'disable']);
                        Route::post('/backup-code-regenerate', [TwoFactorController::class, 'regenerateBackupCodes']);
                    });
            });

        // User routes
        Route::prefix('/user')
            ->group(function () {
                Route::get('/', [UserController::class, 'indexUsers']);
                Route::get('/{id}', [UserController::class, 'getUser']);
                Route::post('/create-new', [UserController::class, 'createNewUser']);
                Route::delete('/destroy/{id}', [UserController::class, 'destroy']);
                Route::post('/update/{userId}', [UserController::class, 'updateUser']);
                Route::delete('/delete/{userId}', [UserController::class, 'deleteUser']);
                Route::put('/change-state/{userId}', [UserController::class, 'changeState']);
                Route::put('/change-status/{userId}', [UserController::class, 'changeStatus']);
                Route::get('/{userId}/logs', [UserController::class, 'getUserLogs']);
                Route::post('/bulk-action', [UserController::class, 'handleBulkAction']);

                // User role bindings
                Route::post('/{userId}/assign-role', [RoleController::class, 'assignRoleToUser']);
                Route::get('/role-binding/{bindingId}', [RoleController::class, 'getUserRoleBinding']);
                Route::delete('/role-binding/{bindingId}/revoke', [RoleController::class, 'revokeUserRoleBinding']);

                // User permission overrides
                Route::post('/{userId}/assign-permission', [RoleController::class, 'assignPermissionToUser']);
                Route::get('/{userId}/permission-overrides', [RoleController::class, 'getUserPermissionOverrides']);

                // Assigned Permissions
                Route::get('/{userId}/role-inherited-permissions', [RoleController::class, 'getRoleInheritedPermissions']);
                Route::post('/{userId}/revoke-inherited-permission', [RoleController::class, 'revokeInheritedPermission']);
                Route::post('/{userId}/restore-inherited-permission', [RoleController::class, 'restoreInheritedPermission']);
                Route::delete('/{userId}/permission-override/{overrideId}', [RoleController::class, 'deleteUserPermissionOverride']);
            });

        // Role routes
        Route::prefix('/role')
            ->group(function () {
                Route::get('/', [RoleController::class, 'index']);
                Route::get('/stats', [RoleController::class, 'stats']);
                Route::post('/create', [RoleController::class, 'store']);
                Route::post('/update/{roleId}', [RoleController::class, 'update']);
                Route::delete('/delete/{roleId}', [RoleController::class, 'destroy']);
                Route::get('/change-state/{roleId}', [RoleController::class, 'changeState']);
                Route::post('/bulk-action', [RoleController::class, 'handleBulkAction']);
                Route::get('/change-type/{roleId}', [RoleController::class, 'changeRoleType']);
                Route::post('/assign-role-to-user/{userId}', [RoleController::class, 'assignRoleToUser']);

                // Role permissions
                Route::get('/{roleId}/permissions', [RoleController::class, 'showPermissions']);
                Route::get('/{roleId}/permission-groups', [RoleController::class, 'getRolePermissionGroups']);
                Route::post('/{roleId}/permissions/add', [RoleController::class, 'addPermissions']);
                Route::post('/{roleId}/permissions/remove', [RoleController::class, 'removePermissions']);
                Route::post('/{roleId}/permissions/set', [RoleController::class, 'setPermissions']);
            });

        // Permission routes
        Route::prefix('/permission')
            ->group(function () {
                Route::get('/', [PermissionController::class, 'index']);
                Route::post('/create', [PermissionController::class, 'store']);
                Route::post('/update/{permissionId}', [PermissionController::class, 'update']);
                Route::delete('/delete/{permissionId}', [PermissionController::class, 'destroy']);
                Route::get('/change-state/{permissionId}', [PermissionController::class, 'changeState']);
                Route::post('/bulk-action', [PermissionController::class, 'handleBulkAction']);
            });

        // Permission group routes
        Route::prefix('/permission-group')
            ->group(function () {
                Route::get('/', [PermissionGroupController::class, 'index']);
                Route::get('/{permissionGroupId}', [PermissionGroupController::class, 'show']);
                Route::post('/create', [PermissionGroupController::class, 'store']);
                Route::post('/update/{permissionGroupId}', [PermissionGroupController::class, 'update']);
                Route::delete('/delete/{permissionGroupId}', [PermissionGroupController::class, 'destroy']);
            });

        Route::get('/constants/gender', [ConstantsIndexController::class, 'getGender']);

        // Sample feature -- class & exam schedules
        Route::prefix('/schedule')
            ->group(function () {
                Route::get('/class-schedules', [ClassScheduleController::class, 'index']);
                Route::post('/class-schedules', [ClassScheduleController::class, 'store']);
                // Lookup by numeric id OR uuid — see ClassScheduleController::show().
                Route::get('/class-schedules/{key}', [ClassScheduleController::class, 'show'])
                    ->where('key', '[A-Za-z0-9-]+');
                Route::put('/class-schedules/{id}', [ClassScheduleController::class, 'update']);
                Route::delete('/class-schedules/{id}', [ClassScheduleController::class, 'destroy']);
                Route::post('/class-schedules/{id}/state', [ClassScheduleController::class, 'changeState']);
            });

        // Lookup routes
        Route::prefix('/lookup')
            ->group(function () {
                // Lookup Types
                Route::apiResource('/types', LookupTypeController::class);
                Route::post('/types/{lookupType}/change-state', [LookupTypeController::class, 'changeState']);
                Route::post('/types/{lookupType}/set-status', [LookupTypeController::class, 'setStatus']);
                Route::get('/get-type-values/{lookupTypeId}', [LookupTypeController::class, 'getTypeValues']);

                // Lookup Values
                Route::post('/values/bulk', [LookupValueController::class, 'storeBulk']);
                Route::apiResource('/values', LookupValueController::class);
                Route::post('/values/{lookupValue}/change-state', [LookupValueController::class, 'changeState']);
                Route::post('/types/{lookupTypeId}/values/reorder', [LookupValueController::class, 'reorder']);
                Route::post('/values/{lookupValue}/change-status', [LookupValueController::class, 'changeStatus']);

                // Lookup Transitions
                Route::get('/transitions-for-type', [LookupTransitionController::class, 'forType']);
                Route::apiResource('/transitions', LookupTransitionController::class);
            });
    });
