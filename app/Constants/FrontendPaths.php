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

    // Master data (physical resources) routes
    public const CAMPUSES = '/campuses';
    public const BUILDINGS = '/buildings';

    // Master data (academic resources) routes
    public const COLLEGES = '/colleges';
    public const DEPARTMENTS = '/departments';
    public const ACADEMIC_YEARS = '/academic-years';
    public const PROGRAMS = '/programs';
    public const SEMESTERS = '/semesters';
    public const INSTRUCTORS = '/instructors';
    public const SECTIONS = '/sections';
    public const COURSES = '/courses';
    public const ROOMS = '/rooms';

    /**
     * Master data detail routes.
     *
     * Every list is paired with a `:uuid` page. Seeing the record is the same
     * right as seeing the list, so each is gated on that entity's `see:` key
     * alone (config/frontend_routes.php).
     */
    public const CAMPUS_DETAIL = '/campuses/:uuid';
    public const BUILDING_DETAIL = '/buildings/:uuid';
    public const COLLEGE_DETAIL = '/colleges/:uuid';
    public const DEPARTMENT_DETAIL = '/departments/:uuid';
    public const ACADEMIC_YEAR_DETAIL = '/academic-years/:uuid';
    public const PROGRAM_DETAIL = '/programs/:uuid';
    public const SEMESTER_DETAIL = '/semesters/:uuid';
    public const INSTRUCTOR_DETAIL = '/instructors/:uuid';
    public const SECTION_DETAIL = '/sections/:uuid';
    public const COURSE_DETAIL = '/courses/:uuid';
    public const ROOM_DETAIL = '/rooms/:uuid';

    // Offering & approval routes
    public const OFFERINGS = '/offerings';
    public const OFFERING_DETAIL = '/offerings/:uuid';

    // Scheduling routes
    public const CLASS_SCHEDULES = '/scheduling/classes';
    public const EXAM_SCHEDULES = '/scheduling/exams';
    public const CLASS_SCHEDULE_DETAIL = '/scheduling/classes/:uuid';
    public const EXAM_SCHEDULE_DETAIL = '/scheduling/exams/:uuid';
    public const GENERATION_RUN_DETAIL = '/scheduling/generation-runs/:uuid';

    /** Invigilation */
    public const INVIGILATOR_AVAILABILITIES = '/invigilation/availabilities';
    public const INVIGILATOR_ASSIGNMENTS = '/invigilation/assignments';

    /** Read-only published views */
    public const TIMETABLE = '/timetable';
    public const EXAM_CALENDAR = '/exam-calendar';

    // Reporting & notification routes
    public const REPORTS = '/reports';
    public const NOTIFICATIONS = '/notifications';
}
