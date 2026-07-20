<?php

namespace App\Constants;

class FrontendPaths {
    public const HOME = '/';
    public const LOGIN = '/login';
    public const USER_PREFIX = '/users';
    public const ADMIN_PREFIX = '/admins';
    public const DASHBOARD = '/dashboard';
    public const VERIFY_ACCOUNT = '/verify-account';
    public const FORGOT_PASSWORD = '/forgot-password';

    // Permission Related Routes
    public const MANAGE_USERS = self::USER_PREFIX . '/manage-users';
    public const MANAGE_PERMISSIONS = self::USER_PREFIX . '/manage-permissions';
    public const PERMISSION_GROUP = self::USER_PREFIX . '/create-permission-group';

    /** Role Related Routes */
    public const MANAGE_ROLES = self::USER_PREFIX . '/manage-roles';
    public const CREATE_ROLE = self::USER_PREFIX . '/create-role';
    public const CREATE_ROLE_PERMISSION = self::USER_PREFIX . '/manage-roles/create';
    public const ROLE_PERMISSIONS = self::USER_PREFIX . '/manage-roles/:id/permissions';
    public const ASSIGN_ROLE = self::USER_PREFIX . '/user-role-binding';

    // User Profile related routes
    public const MANAGE_PROFILE = self::USER_PREFIX . '/user-profile';

    // Lookup (dynamic value) related routes
    public const MANAGE_DYNAMIC_VALUES = self::ADMIN_PREFIX . '/dynamic-values';

    // Master data (academic resources) routes
    public const COLLEGES = '/colleges';
    public const DEPARTMENTS = '/departments';
    public const INSTRUCTORS = '/instructors';
    public const CLASSES = '/classes';
    public const COURSES = '/courses';
    public const ROOMS = '/rooms';

    // Scheduling routes
    public const CLASS_SCHEDULES = '/scheduling/classes';
    public const EXAM_SCHEDULES = '/scheduling/exams';

    // Reporting & notification routes
    public const REPORTS = '/reports';
    public const NOTIFICATIONS = '/notifications';
}
