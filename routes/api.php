<?php

use App\Http\Controllers\AcademicYear\AcademicYearController;
use App\Http\Controllers\Auth\DeviceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Building\BuildingController;
use App\Http\Controllers\Campus\CampusController;
use App\Http\Controllers\College\CollegeController;
use App\Http\Controllers\Constant\ConstantsIndexController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\CourseOffering\CourseOfferingController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\Instructor\InstructorController;
use App\Http\Controllers\Invigilation\ExamInvigilatorAssignmentController;
use App\Http\Controllers\Invigilation\InvigilationRequestController;
use App\Http\Controllers\Lang\LanguageController;
use App\Http\Controllers\Lookup\LookupTransitionController;
use App\Http\Controllers\Lookup\LookupTypeController;
use App\Http\Controllers\Lookup\LookupValueController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Permission\PermissionGroupController;
use App\Http\Controllers\Program\ProgramController;
use App\Http\Controllers\Report\ScheduleReportController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\Room\RoomController;
use App\Http\Controllers\Schedule\ClassScheduleController;
use App\Http\Controllers\Schedule\ClassScheduleGeneratorController;
use App\Http\Controllers\Schedule\ExamScheduleController;
use App\Http\Controllers\Schedule\ExamScheduleGeneratorController;
use App\Http\Controllers\Schedule\ScheduleGenerationRunController;
use App\Http\Controllers\Schedule\ScheduleSettingController;
use App\Http\Controllers\Section\SectionController;
use App\Http\Controllers\Semester\SemesterController;
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

Route::post('/login', [LoginController::class, 'login']);
// ->middleware('rate_limit:' . LOGIN_RATE_LIMIT_ATTEMPTS . ',' . LOGIN_RATE_LIMIT_DECAY);

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
                // Issues a NEW password and mails it — the old one stops
                // working, which is the point when credentials went astray.
                Route::post('/{userId}/resend-password', [UserController::class, 'resendPassword']);
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

        // Read-only aggregates for the landing screen.
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        Route::get('/constants/gender', [ConstantsIndexController::class, 'getGender']);
        Route::get('/constants/scheduling', [ConstantsIndexController::class, 'getScheduling']);

        // Master data -- physical resources.
        // ->parameters() renames the placeholder to {id} so Form Request
        // authorize() can branch on $this->route('id'); the regex lets show()
        // take a numeric id OR a uuid (see CLAUDE Sec. 10.18).
        Route::apiResource('/campuses', CampusController::class)
            ->parameters(['campuses' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/campuses/{id}/state', [CampusController::class, 'changeState']);

        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /buildings/export
        // would be swallowed by show() with id = "export".
        Route::get('/buildings/export', [BuildingController::class, 'export']);
        Route::get('/buildings/import-template', [BuildingController::class, 'importTemplate']);
        Route::post('/buildings/import', [BuildingController::class, 'import']);

        Route::apiResource('/buildings', BuildingController::class)
            ->parameters(['buildings' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/buildings/{id}/state', [BuildingController::class, 'changeState']);

        // Master data -- academic hierarchy
        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /colleges/export
        // would be swallowed by show() with id = "export".
        Route::get('/colleges/export', [CollegeController::class, 'export']);
        Route::get('/colleges/import-template', [CollegeController::class, 'importTemplate']);
        Route::post('/colleges/import', [CollegeController::class, 'import']);

        Route::apiResource('/colleges', CollegeController::class)
            ->parameters(['colleges' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/colleges/{id}/state', [CollegeController::class, 'changeState']);

        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /departments/export
        // would be swallowed by show() with id = "export".
        Route::get('/departments/export', [DepartmentController::class, 'export']);
        Route::get('/departments/import-template', [DepartmentController::class, 'importTemplate']);
        Route::post('/departments/import', [DepartmentController::class, 'import']);

        Route::apiResource('/departments', DepartmentController::class)
            ->parameters(['departments' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/departments/{id}/state', [DepartmentController::class, 'changeState']);

        // Academic years carry neither is_active nor state, so there is no
        // /{id}/state route here (Final Schema.md Sec. 6).
        Route::apiResource('/academic-years', AcademicYearController::class)
            ->parameters(['academic-years' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);

        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /programs/export
        // would be swallowed by show() with id = "export".
        Route::get('/programs/export', [ProgramController::class, 'export']);
        Route::get('/programs/import-template', [ProgramController::class, 'importTemplate']);
        Route::post('/programs/import', [ProgramController::class, 'import']);

        Route::apiResource('/programs', ProgramController::class)
            ->parameters(['programs' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/programs/{id}/state', [ProgramController::class, 'changeState']);

        // Semesters have no is_active and no state — the only lifecycle move is
        // change-status, guarded by lookup_transitions (Final Schema.md Sec. 7).
        Route::apiResource('/semesters', SemesterController::class)
            ->parameters(['semesters' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/semesters/{id}/change-status', [SemesterController::class, 'changeStatus']);

        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /sections/export
        // would be swallowed by show() with id = "export".
        Route::get('/sections/export', [SectionController::class, 'export']);
        Route::get('/sections/import-template', [SectionController::class, 'importTemplate']);
        Route::post('/sections/import', [SectionController::class, 'import']);

        Route::apiResource('/sections', SectionController::class)
            ->parameters(['sections' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/sections/{id}/state', [SectionController::class, 'changeState']);

        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /rooms/export
        // would be swallowed by show() with id = "export".
        Route::get('/rooms/export', [RoomController::class, 'export']);
        Route::get('/rooms/import-template', [RoomController::class, 'importTemplate']);
        Route::post('/rooms/import', [RoomController::class, 'import']);

        Route::apiResource('/rooms', RoomController::class)
            ->parameters(['rooms' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/rooms/{id}/state', [RoomController::class, 'changeState']);

        // Catalogue & people
        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /courses/export
        // would be swallowed by show() with id = "export".
        Route::get('/courses/export', [CourseController::class, 'export']);
        Route::get('/courses/import-template', [CourseController::class, 'importTemplate']);
        Route::post('/courses/import', [CourseController::class, 'import']);

        Route::apiResource('/courses', CourseController::class)
            ->parameters(['courses' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/courses/{id}/state', [CourseController::class, 'changeState']);

        // Import / export. Declared BEFORE the apiResource: the resource's
        // {id} placeholder matches [A-Za-z0-9-]+, so a later /instructors/export
        // would be swallowed by show() with id = "export".
        Route::get('/instructors/export', [InstructorController::class, 'export']);
        Route::get('/instructors/import-template', [InstructorController::class, 'importTemplate']);
        Route::post('/instructors/import', [InstructorController::class, 'import']);

        Route::apiResource('/instructors', InstructorController::class)
            ->parameters(['instructors' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/instructors/{id}/state', [InstructorController::class, 'changeState']);

        // Offering & approval. A workflow table: no /{id}/state route — the
        // status moves through submit and the approval trail, both guarded by
        // lookup_transitions (Final Schema.md Sec. 12).
        // Import / export + the queue summary. Declared BEFORE the apiResource:
        // its {id} placeholder matches [A-Za-z0-9-]+ and would otherwise
        // swallow these as show() with id = "export".
        Route::get('/offerings/export', [CourseOfferingController::class, 'export']);
        Route::get('/offerings/import-template', [CourseOfferingController::class, 'importTemplate']);
        Route::post('/offerings/import', [CourseOfferingController::class, 'import']);
        Route::get('/offerings/summary', [CourseOfferingController::class, 'summary']);

        Route::apiResource('/offerings', CourseOfferingController::class)
            ->parameters(['offerings' => 'id'])
            ->where(['id' => '[A-Za-z0-9-]+']);
        Route::post('/offerings/{id}/submit', [CourseOfferingController::class, 'submit']);
        // Replaces the old generic change-status, which accepted any target and
        // wrote no trail row. This one takes no target: `rejected → draft` only.
        Route::post('/offerings/{id}/reopen', [CourseOfferingController::class, 'reopen']);
        // The four-tier trail: one append-only row per decision, which also
        // moves the offering's status (Final Schema.md Sec. 13).
        // One decision over many rows — see the controller for why a bulk
        // run re-checks every per-row rule rather than mass-updating.
        Route::post('/offerings/bulk-action', [CourseOfferingController::class, 'bulkAction']);
        Route::post('/offerings/{id}/approval', [CourseOfferingController::class, 'recordApproval']);

        // Class scheduling. No /{id}/state route: `state` is the
        // conflict-liveness flag and only ever moves with the status, through
        // publish or cancel (Final Schema.md Sec. 14).
        Route::prefix('/schedule')
            ->group(function () {
                Route::post('/generate-class', [ClassScheduleGeneratorController::class, 'generate']);

                // Where an unplaced offering would fit. Declared before the
                // apiResource so "suggestions" is not swallowed by {id}.
                Route::get('/class-schedules/suggestions', [ClassScheduleController::class, 'suggestions']);
                // Bulk moves: shift a programme by a day, empty a room, cancel
                // a holiday week. Declared before the apiResource so the path
                // is not swallowed by {id}.
                Route::post('/class-schedules/bulk', [ClassScheduleController::class, 'bulk']);
                // Lifecycle decisions (publish / confirm / cancel / delete) over
                // many meetings. Distinct from `/bulk` above, which does bulk
                // EDITS — shifting days, swapping rooms.
                Route::post('/class-schedules/bulk-action', [ClassScheduleController::class, 'bulkAction']);

                Route::apiResource('/class-schedules', ClassScheduleController::class)
                    ->parameters(['class-schedules' => 'id'])
                    ->where(['id' => '[A-Za-z0-9-]+']);
                // The department confirmation step, mirroring exams (C26).
                Route::post('/class-schedules/{id}/confirm', [ClassScheduleController::class, 'confirm']);
                Route::post('/class-schedules/{id}/return-to-draft', [ClassScheduleController::class, 'returnToDraft']);
                Route::post('/class-schedules/{id}/publish', [ClassScheduleController::class, 'publish']);
                Route::post('/class-schedules/{id}/cancel', [ClassScheduleController::class, 'cancel']);
                // Keep a hand-placed session through the next generation run.
                Route::post('/class-schedules/{id}/pin', [ClassScheduleController::class, 'pin']);
                // Cancel a single week without cancelling the weekly rule.
                Route::post('/class-schedules/{id}/exceptions', [ClassScheduleController::class, 'addException']);
                Route::delete('/class-schedules/{id}/exceptions/{exceptionId}', [ClassScheduleController::class, 'removeException']);

                // Exam scheduling. Same shape as class scheduling, plus the
                // optional department-confirmation step (Final Schema.md Sec. 15).
                Route::post('/generate-exam', [ExamScheduleGeneratorController::class, 'generate']);

                Route::apiResource('/exam-schedules', ExamScheduleController::class)
                    ->parameters(['exam-schedules' => 'id'])
                    ->where(['id' => '[A-Za-z0-9-]+']);
                Route::post('/exam-schedules/bulk-action', [ExamScheduleController::class, 'bulkAction']);
                Route::post('/exam-schedules/{id}/confirm', [ExamScheduleController::class, 'confirm']);
                Route::post('/exam-schedules/{id}/publish', [ExamScheduleController::class, 'publish']);
                Route::post('/exam-schedules/{id}/cancel', [ExamScheduleController::class, 'cancel']);
                Route::post('/exam-schedules/{id}/pin', [ExamScheduleController::class, 'pin']);

                // The generation grid per study mode — what the registrar
                // edits under Configuration instead of a redeploy. No delete:
                // a grid belongs to a seeded mode and is deactivated instead.
                Route::apiResource('/settings', ScheduleSettingController::class)
                    ->only(['index', 'show', 'store', 'update'])
                    ->parameters(['settings' => 'id'])
                    ->where(['id' => '[A-Za-z0-9-]+']);

                // Run history — telemetry the progress UI polls.
                Route::apiResource('/generation-runs', ScheduleGenerationRunController::class)
                    ->only(['index', 'show'])
                    ->parameters(['generation-runs' => 'id'])
                    ->where(['id' => '[A-Za-z0-9-]+']);

                // Put back the timetable a regeneration replaced. The snapshot
                // is replayed through the normal service, so every EXCLUDE
                // still applies — restoring cannot write an illegal row.
                Route::post('/generation-runs/{id}/restore', [ScheduleGenerationRunController::class, 'restore']);
            });

        // Reporting. Read-only: three fixed reports and the exceptions list,
        // each one an aggregate over tables that already exist.
        Route::prefix('/reports')
            ->group(function () {
                Route::get('/room-utilisation', [ScheduleReportController::class, 'roomUtilisation']);
                Route::get('/instructor-workload', [ScheduleReportController::class, 'instructorWorkload']);
                Route::get('/exceptions', [ScheduleReportController::class, 'exceptions']);
                Route::get('/compare', [ScheduleReportController::class, 'compare']);
                // Is this term ready to schedule? The status page that replaces
                // knowing the dependency order by heart (C37).
                Route::get('/term-setup', [ScheduleReportController::class, 'termSetup']);
            });

        // Invigilation. An availability window is a positive statement, not a
        // record to revise — no update action, no state, no status
        // (Final Schema.md Sec. 17).
        Route::prefix('/invigilation')
            ->group(function () {
                // The request/response exchange: the registrar asks departments
                // for people, each with its own quantity; departments answer.
                // The people they send become the pool exam staffing draws from.
                Route::apiResource('/requests', InvigilationRequestController::class)
                    ->only(['index', 'show', 'store', 'update'])
                    ->parameters(['requests' => 'id'])
                    ->where(['id' => '[A-Za-z0-9-]+']);
                Route::post('/requests/{id}/send', [InvigilationRequestController::class, 'send']);
                Route::post('/requests/{id}/close', [InvigilationRequestController::class, 'close']);
                // Keyed by the DEPARTMENT'S SHARE, not the request: a department
                // answers its own line, never the whole ask.
                Route::post('/request-departments/{id}/submit', [InvigilationRequestController::class, 'submit']);
                Route::delete('/submissions/{id}', [InvigilationRequestController::class, 'withdraw']);


                // Duties. No /{id}/state route: `state` is the conflict-liveness
                // flag and moves only with the status — declining or being
                // replaced frees the invigilator (Final Schema.md Sec. 18).
                Route::post('/auto-assign', [ExamInvigilatorAssignmentController::class, 'autoAssign']);
                Route::get('/assignments', [ExamInvigilatorAssignmentController::class, 'index']);
                Route::post('/assignments', [ExamInvigilatorAssignmentController::class, 'store']);
                Route::post('/assignments/{id}/respond', [ExamInvigilatorAssignmentController::class, 'respond']);
                Route::post('/assignments/{id}/replace', [ExamInvigilatorAssignmentController::class, 'replace']);
                // Take somebody off a hall. Only a duty nobody has answered.
                Route::delete('/assignments/{id}', [ExamInvigilatorAssignmentController::class, 'destroy']);
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
