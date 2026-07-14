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

// Class / Exam Schedule Related (sample feature)
define('PERMISSION_SEE_CLASS_SCHEDULE', 'see:class:schedule');
define('PERMISSION_CREATE_CLASS_SCHEDULE', 'create:class:schedule');
define('PERMISSION_UPDATE_CLASS_SCHEDULE', 'update:class:schedule');
define('PERMISSION_DELETE_CLASS_SCHEDULE', 'delete:class:schedule');
define('PERMISSION_CHANGE_CLASS_SCHEDULE_STATE', 'change:class:schedule:state');

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
