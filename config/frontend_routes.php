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

                /** Master Data (physical resources) Routes */
                FrontendPaths::CAMPUSES => [PERMISSION_SEE_CAMPUS, PERMISSION_CREATE_CAMPUS, PERMISSION_UPDATE_CAMPUS],
                FrontendPaths::BUILDINGS => [PERMISSION_SEE_BUILDING, PERMISSION_CREATE_BUILDING, PERMISSION_UPDATE_BUILDING],

                /** Master Data (academic resources) Routes */
                FrontendPaths::COLLEGES => [PERMISSION_SEE_COLLEGE, PERMISSION_CREATE_COLLEGE, PERMISSION_UPDATE_COLLEGE],
                FrontendPaths::DEPARTMENTS => [PERMISSION_SEE_DEPARTMENT, PERMISSION_CREATE_DEPARTMENT, PERMISSION_UPDATE_DEPARTMENT],
                FrontendPaths::ACADEMIC_YEARS => [PERMISSION_SEE_ACADEMIC_YEAR, PERMISSION_CREATE_ACADEMIC_YEAR, PERMISSION_UPDATE_ACADEMIC_YEAR],
                FrontendPaths::PROGRAMS => [PERMISSION_SEE_PROGRAM, PERMISSION_CREATE_PROGRAM, PERMISSION_UPDATE_PROGRAM],
                FrontendPaths::SEMESTERS => [PERMISSION_SEE_SEMESTER, PERMISSION_CREATE_SEMESTER, PERMISSION_UPDATE_SEMESTER],
                FrontendPaths::INSTRUCTORS => [PERMISSION_SEE_INSTRUCTOR, PERMISSION_CREATE_INSTRUCTOR, PERMISSION_UPDATE_INSTRUCTOR],
                FrontendPaths::SECTIONS => [PERMISSION_SEE_SECTION, PERMISSION_CREATE_SECTION, PERMISSION_UPDATE_SECTION],
                FrontendPaths::COURSES => [PERMISSION_SEE_COURSE, PERMISSION_CREATE_COURSE, PERMISSION_UPDATE_COURSE],
                FrontendPaths::ROOMS => [PERMISSION_SEE_ROOM, PERMISSION_CREATE_ROOM, PERMISSION_UPDATE_ROOM],

                /** Master data detail routes — seeing the record is the same right as seeing the list */
                FrontendPaths::CAMPUS_DETAIL => [PERMISSION_SEE_CAMPUS],
                FrontendPaths::BUILDING_DETAIL => [PERMISSION_SEE_BUILDING],
                FrontendPaths::COLLEGE_DETAIL => [PERMISSION_SEE_COLLEGE],
                FrontendPaths::DEPARTMENT_DETAIL => [PERMISSION_SEE_DEPARTMENT],
                FrontendPaths::ACADEMIC_YEAR_DETAIL => [PERMISSION_SEE_ACADEMIC_YEAR],
                FrontendPaths::PROGRAM_DETAIL => [PERMISSION_SEE_PROGRAM],
                FrontendPaths::SEMESTER_DETAIL => [PERMISSION_SEE_SEMESTER],
                FrontendPaths::INSTRUCTOR_DETAIL => [PERMISSION_SEE_INSTRUCTOR],
                FrontendPaths::SECTION_DETAIL => [PERMISSION_SEE_SECTION],
                FrontendPaths::COURSE_DETAIL => [PERMISSION_SEE_COURSE],
                FrontendPaths::ROOM_DETAIL => [PERMISSION_SEE_ROOM],

                /** Offering & Approval Routes */
                FrontendPaths::OFFERINGS => [PERMISSION_SEE_COURSE_OFFERING, PERMISSION_CREATE_COURSE_OFFERING, PERMISSION_UPDATE_COURSE_OFFERING],
                FrontendPaths::OFFERING_DETAIL => [PERMISSION_SEE_COURSE_OFFERING],

                /** Scheduling Routes */
                FrontendPaths::SCHEDULE_SETTINGS => [PERMISSION_SEE_SCHEDULE_SETTING, PERMISSION_UPDATE_SCHEDULE_SETTING],
                FrontendPaths::CLASS_SCHEDULES => [PERMISSION_SEE_CLASS_SCHEDULE, PERMISSION_CREATE_CLASS_SCHEDULE, PERMISSION_UPDATE_CLASS_SCHEDULE],
                FrontendPaths::EXAM_SCHEDULES => [PERMISSION_SEE_EXAM_SCHEDULE, PERMISSION_CREATE_EXAM_SCHEDULE, PERMISSION_UPDATE_EXAM_SCHEDULE],
                FrontendPaths::CLASS_SCHEDULE_DETAIL => [PERMISSION_SEE_CLASS_SCHEDULE],
                FrontendPaths::EXAM_SCHEDULE_DETAIL => [PERMISSION_SEE_EXAM_SCHEDULE],
                FrontendPaths::GENERATION_RUN_DETAIL => [PERMISSION_SEE_SCHEDULE_GENERATION_RUN],

                /** Invigilation Routes */
                FrontendPaths::INVIGILATION_REQUESTS => [PERMISSION_SEE_INVIGILATION_REQUEST, PERMISSION_CREATE_INVIGILATION_REQUEST, PERMISSION_RESPOND_TO_INVIGILATION_REQUEST],
                FrontendPaths::INVIGILATOR_ASSIGNMENTS => [PERMISSION_SEE_INVIGILATOR_ASSIGNMENT, PERMISSION_ASSIGN_INVIGILATOR, PERMISSION_RESPOND_TO_INVIGILATOR_ASSIGNMENT],

                /** Published, read-only views — seeing the timetable is enough */
                FrontendPaths::TIMETABLE => [PERMISSION_SEE_CLASS_SCHEDULE],
                FrontendPaths::EXAM_CALENDAR => [PERMISSION_SEE_EXAM_SCHEDULE],

                /** Reporting & Notification Routes */
                FrontendPaths::REPORTS => [PERMISSION_SEE_REPORT],
                FrontendPaths::NOTIFICATIONS => [PERMISSION_SEE_NOTIFICATION],
        ],

        'auth_redirect' => FrontendPaths::DASHBOARD,
        'unauth_redirect' => FrontendPaths::HOME,
];
