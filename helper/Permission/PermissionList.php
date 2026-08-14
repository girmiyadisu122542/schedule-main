<?php

/**
 * The List of permission that are
 * going to be used in the system
 */

define('PERMISSION_SEE_DASHBOARD', 'see:dashboard');

/** User Relation Permission */
define('PERMISSION_SEE_USER', 'see:user');
define('PERMISSION_CREATE_USER', 'create:user');
define('PERMISSION_DELETE_USER', 'delete:user');
define('PERMISSION_CHANGE_USER_STATUS', 'change:user:status');
define('PERMISSION_CHANGE_USER_STATE', 'change:user:state');
define('PERMISSION_UPDATE_USER', 'update:user');
define('PERMISSION_SEE_NOT_ASSIGNED_USERS', 'see:not:assigned:users');
define('PERMISSION_MANAGE_USER_SESSIONS', 'manage:user:sessions');

/** Permission Group Related */
define('PERMISSION_SEE_PERMISSION_GROUP', 'see:permission:group');
define('PERMISSION_CREATE_PERMISSION_GROUP', 'create:permission:group');
define('PERMISSION_UPDATE_PERMISSION_GROUP', 'update:permission:group');
define('PERMISSION_DELETE_PERMISSION_GROUP', 'delete:permission:group');

/** Permission Related */
define('PERMISSION_SEE_PERMISSION', 'see:permission');
define('PERMISSION_CREATE_PERMISSION', 'create:permission');
define('PERMISSION_UPDATE_PERMISSION', 'update:permission');
define('PERMISSION_DELETE_PERMISSION', 'delete:permission');
define('PERMISSION_CHANGE_PERMISSION_STATUS', 'change:permission:status');

/** Role Related */
define('PERMISSION_SEE_ROLE', 'see:role');
define('PERMISSION_CREATE_ROLE', 'create:role');
define('PERMISSION_UPDATE_ROLE', 'update:role');
define('PERMISSION_DELETE_ROLE', 'delete:role');
define('PERMISSION_CHANGE_ROLE_TYPE', 'change:role:type');
define('PERMISSION_CHANGE_ROLE_STATE', 'change:role:state');
define('PERMISSION_CHANGE_ROLE_STATUS', 'change:role:status');
define('PERMISSION_ASSIGN_ROLE_TO_USER', 'assign:role:to:user');
define('PERMISSION_EDIT_USER_ROLE_BINDING', 'edit:user:role:binding');
define('PERMISSION_REMOVE_ROLE_FROM_USER', 'remove:role:from:user');
define('PERMISSION_ADD_ROLE_PERMISSION', 'add:role:permission');
define('PERMISSION_SEE_ROLE_PERMISSION', 'see:role:permission');
define('PERMISSION_REMOVE_ROLE_PERMISSION', 'remove:role:permission');
define('PERMISSION_ASSIGN_PERMISSION_TO_USER', 'assign:permission:to:user');
define('PERMISSION_ENTITY_ADD_ROLE', 'entity:add:role');

// User Profile Related
define('PERMISSION_SEE_PROFILE', 'see:profile');
define('PERMISSION_UPDATE_PROFILE', 'update:profile');

// Class Schedule Related
// No `change:class:schedule:state` key: `state` is never toggled on its own
// here — it moves together with the status through publish / cancel.
define('PERMISSION_SEE_CLASS_SCHEDULE', 'see:class:schedule');
define('PERMISSION_CREATE_CLASS_SCHEDULE', 'create:class:schedule');
define('PERMISSION_UPDATE_CLASS_SCHEDULE', 'update:class:schedule');
define('PERMISSION_DELETE_CLASS_SCHEDULE', 'delete:class:schedule');
define('PERMISSION_PUBLISH_CLASS_SCHEDULE', 'publish:class:schedule');
define('PERMISSION_CANCEL_CLASS_SCHEDULE', 'cancel:class:schedule');

// Schedule generation
define('PERMISSION_CONFIRM_CLASS_SCHEDULE', 'confirm:class:schedule');
define('PERMISSION_RUN_CLASS_SCHEDULE_GENERATION', 'run:class:schedule:generation');
define('PERMISSION_RUN_EXAM_SCHEDULE_GENERATION', 'run:exam:schedule:generation');
define('PERMISSION_SEE_SCHEDULE_GENERATION_RUN', 'see:schedule:generation:run');

// Exam Schedule Related
// No `change:exam:schedule:state` key, for the same reason as class schedules:
// `state` is the conflict-liveness flag and only ever moves with the status.
define('PERMISSION_SEE_EXAM_SCHEDULE', 'see:exam:schedule');
define('PERMISSION_CREATE_EXAM_SCHEDULE', 'create:exam:schedule');
define('PERMISSION_UPDATE_EXAM_SCHEDULE', 'update:exam:schedule');
define('PERMISSION_DELETE_EXAM_SCHEDULE', 'delete:exam:schedule');
define('PERMISSION_CONFIRM_EXAM_SCHEDULE', 'confirm:exam:schedule');
define('PERMISSION_PUBLISH_EXAM_SCHEDULE', 'publish:exam:schedule');
define('PERMISSION_CANCEL_EXAM_SCHEDULE', 'cancel:exam:schedule');

// Invigilation Related
// An availability window is a statement, not a record to revise — hence
// `submit` rather than `create`/`update`.
/**
 * The invigilator request/response exchange.
 *
 * `send` is separate from `create` so a clerk can prepare an ask that only a
 * registrar may actually issue. `respond` is the department's side; which
 * department a user speaks for is a data question answered by
 * `DepartmentScopeService`, not another permission key.
 */
define('PERMISSION_SEE_INVIGILATION_REQUEST', 'see:invigilation:request');
define('PERMISSION_CREATE_INVIGILATION_REQUEST', 'create:invigilation:request');
define('PERMISSION_UPDATE_INVIGILATION_REQUEST', 'update:invigilation:request');
define('PERMISSION_SEND_INVIGILATION_REQUEST', 'send:invigilation:request');
define('PERMISSION_RESPOND_TO_INVIGILATION_REQUEST', 'respond:to:invigilation:request');

define('PERMISSION_SEE_INVIGILATOR_ASSIGNMENT', 'see:invigilator:assignment');
define('PERMISSION_ASSIGN_INVIGILATOR', 'assign:invigilator');
define('PERMISSION_RESPOND_TO_INVIGILATOR_ASSIGNMENT', 'respond:to:invigilator:assignment');
define('PERMISSION_REPLACE_INVIGILATOR', 'replace:invigilator');

/** Master Data: Campus Related */
define('PERMISSION_SEE_CAMPUS', 'see:campus');
define('PERMISSION_CREATE_CAMPUS', 'create:campus');
define('PERMISSION_UPDATE_CAMPUS', 'update:campus');
define('PERMISSION_DELETE_CAMPUS', 'delete:campus');
define('PERMISSION_CHANGE_CAMPUS_STATE', 'change:campus:state');

/** Master Data: Building Related */
define('PERMISSION_SEE_BUILDING', 'see:building');
define('PERMISSION_CREATE_BUILDING', 'create:building');
define('PERMISSION_UPDATE_BUILDING', 'update:building');
define('PERMISSION_DELETE_BUILDING', 'delete:building');
define('PERMISSION_CHANGE_BUILDING_STATE', 'change:building:state');

/** Master Data: College / School Related */
define('PERMISSION_SEE_COLLEGE', 'see:college');
define('PERMISSION_CREATE_COLLEGE', 'create:college');
define('PERMISSION_UPDATE_COLLEGE', 'update:college');
define('PERMISSION_DELETE_COLLEGE', 'delete:college');
define('PERMISSION_CHANGE_COLLEGE_STATE', 'change:college:state');

/** Master Data: Department Related */
define('PERMISSION_SEE_DEPARTMENT', 'see:department');
define('PERMISSION_CREATE_DEPARTMENT', 'create:department');
define('PERMISSION_UPDATE_DEPARTMENT', 'update:department');
define('PERMISSION_DELETE_DEPARTMENT', 'delete:department');
define('PERMISSION_CHANGE_DEPARTMENT_STATE', 'change:department:state');

/** Master Data: Academic Year Related — no state permission, the table has no is_active */
define('PERMISSION_SEE_ACADEMIC_YEAR', 'see:academic:year');
define('PERMISSION_CREATE_ACADEMIC_YEAR', 'create:academic:year');
define('PERMISSION_UPDATE_ACADEMIC_YEAR', 'update:academic:year');
define('PERMISSION_DELETE_ACADEMIC_YEAR', 'delete:academic:year');

/** Master Data: Program Related */
define('PERMISSION_SEE_PROGRAM', 'see:program');
define('PERMISSION_CREATE_PROGRAM', 'create:program');
define('PERMISSION_UPDATE_PROGRAM', 'update:program');
define('PERMISSION_DELETE_PROGRAM', 'delete:program');
define('PERMISSION_CHANGE_PROGRAM_STATE', 'change:program:state');

/** Master Data: Semester Related — no state permission, the status is a guarded lifecycle */
define('PERMISSION_SEE_SEMESTER', 'see:semester');
define('PERMISSION_CREATE_SEMESTER', 'create:semester');
define('PERMISSION_UPDATE_SEMESTER', 'update:semester');
define('PERMISSION_DELETE_SEMESTER', 'delete:semester');
define('PERMISSION_CHANGE_SEMESTER_STATUS', 'change:semester:status');

/** Master Data: Instructor Related */
define('PERMISSION_SEE_INSTRUCTOR', 'see:instructor');
define('PERMISSION_CREATE_INSTRUCTOR', 'create:instructor');
define('PERMISSION_UPDATE_INSTRUCTOR', 'update:instructor');
define('PERMISSION_DELETE_INSTRUCTOR', 'delete:instructor');
define('PERMISSION_CHANGE_INSTRUCTOR_STATE', 'change:instructor:state');

/**
 * Master Data: Section Related.
 *
 * Replaces the starter kit's "class" permission set — `sections` is the schema's
 * student-cohort entity (Final Schema.md §8); there is no `classes` table.
 */
define('PERMISSION_SEE_SECTION', 'see:section');
define('PERMISSION_CREATE_SECTION', 'create:section');
define('PERMISSION_UPDATE_SECTION', 'update:section');
define('PERMISSION_DELETE_SECTION', 'delete:section');
define('PERMISSION_CHANGE_SECTION_STATE', 'change:section:state');

/** Master Data: Course Related */
define('PERMISSION_SEE_COURSE', 'see:course');
define('PERMISSION_CREATE_COURSE', 'create:course');
define('PERMISSION_UPDATE_COURSE', 'update:course');
define('PERMISSION_DELETE_COURSE', 'delete:course');
define('PERMISSION_CHANGE_COURSE_STATE', 'change:course:state');

/** Master Data: Room Related */
define('PERMISSION_SEE_ROOM', 'see:room');
define('PERMISSION_CREATE_ROOM', 'create:room');
define('PERMISSION_UPDATE_ROOM', 'update:room');
define('PERMISSION_DELETE_ROOM', 'delete:room');
define('PERMISSION_CHANGE_ROOM_STATE', 'change:room:state');

/**
 * Master Data: spreadsheet import / export.
 *
 * Deliberately separate from see/create/update: bulk-loading a department list
 * is a different act from editing one row, and a registrar who may correct a
 * typo is not automatically someone who may replace the whole table. Export is
 * its own key for the mirror reason — it takes the entire filtered list out of
 * the system in one file.
 */
define('PERMISSION_EXPORT_COLLEGE', 'export:college');
define('PERMISSION_IMPORT_COLLEGE', 'import:college');
define('PERMISSION_EXPORT_BUILDING', 'export:building');
define('PERMISSION_IMPORT_BUILDING', 'import:building');
define('PERMISSION_EXPORT_DEPARTMENT', 'export:department');
define('PERMISSION_IMPORT_DEPARTMENT', 'import:department');
define('PERMISSION_EXPORT_PROGRAM', 'export:program');
define('PERMISSION_IMPORT_PROGRAM', 'import:program');
define('PERMISSION_EXPORT_SECTION', 'export:section');
define('PERMISSION_IMPORT_SECTION', 'import:section');
define('PERMISSION_EXPORT_ROOM', 'export:room');
define('PERMISSION_IMPORT_ROOM', 'import:room');
define('PERMISSION_EXPORT_COURSE', 'export:course');
define('PERMISSION_IMPORT_COURSE', 'import:course');
define('PERMISSION_EXPORT_INSTRUCTOR', 'export:instructor');
define('PERMISSION_IMPORT_INSTRUCTOR', 'import:instructor');

/**
 * Course Offering Related — the four-tier approval workflow.
 *
 * `approve` and `reject` gate the trail-recording endpoint (step 10); which
 * TIER a user may act as is decided by their role, not by a separate key.
 */
define('PERMISSION_SEE_COURSE_OFFERING', 'see:course:offering');
define('PERMISSION_CREATE_COURSE_OFFERING', 'create:course:offering');
define('PERMISSION_UPDATE_COURSE_OFFERING', 'update:course:offering');
define('PERMISSION_DELETE_COURSE_OFFERING', 'delete:course:offering');
define('PERMISSION_SUBMIT_COURSE_OFFERING', 'submit:course:offering');
define('PERMISSION_APPROVE_COURSE_OFFERING', 'approve:course:offering');
define('PERMISSION_REJECT_COURSE_OFFERING', 'reject:course:offering');

/**
 * The cross-department view.
 *
 * Everyone else is confined to the departments they head, the college they
 * lead, or the department they teach in — see
 * `App\Services\User\DepartmentScopeService`. This key is what lifts that for a
 * role the institution trusts institution-wide (the Registrar); super admins
 * are unrestricted without it.
 */
define('PERMISSION_SEE_ALL_DEPARTMENTS', 'see:all:departments');

/**
 * Scheduling configuration — the generation grid per study mode.
 *
 * There is no delete key: a grid belongs to a study mode and the modes are a
 * seeded system vocabulary, so a grid is edited or deactivated, never removed.
 */
define('PERMISSION_SEE_SCHEDULE_SETTING', 'see:schedule:setting');
define('PERMISSION_CREATE_SCHEDULE_SETTING', 'create:schedule:setting');
define('PERMISSION_UPDATE_SCHEDULE_SETTING', 'update:schedule:setting');

/** Report Related */
define('PERMISSION_SEE_REPORT', 'see:report');
define('PERMISSION_EXPORT_REPORT', 'export:report');

/** Notification Related */
define('PERMISSION_SEE_NOTIFICATION', 'see:notification');
define('PERMISSION_CHANGE_NOTIFICATION_STATUS', 'change:notification:status');

// Dynamic Value Related
define('PERMISSION_SEE_DYNAMIC_VALUE', 'see:dynamic:value');
define('PERMISSION_CREATE_DYNAMIC_VALUE', 'create:dynamic:value');
define('PERMISSION_UPDATE_DYNAMIC_VALUE', 'update:dynamic:value');
define('PERMISSION_DELETE_DYNAMIC_VALUE', 'delete:dynamic:value');
define('PERMISSION_CHANGE_DYNAMIC_VALUE_STATUS', 'change:dynamic:value:status');
define('PERMISSION_CHANGE_DYNAMIC_VALUE_STATE', 'change:dynamic:value:state');
define('PERMISSION_ENTITY_ADD_DYNAMIC_VALUE', 'entity:add:dynamic:value');

/**
 * Leftover: ConstantsIndexController::getModules() still calls
 * userCanSeeModule(). Delete this constant together with that endpoint.
 */
define('PERMISSION_SEE_MODULE', 'see:module');
