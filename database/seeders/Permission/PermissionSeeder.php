<?php

namespace Database\Seeders\Permission;

use App\Models\Permission\Permission;
use App\Models\Permission\PermissionGroup;
use App\Models\User;
use Constants\AppConstant;
use Exception;
use Helper\Translation\AmharicNameComposer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Translation\Back\Amharic;
use Translation\Back\English;

class PermissionSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * Seeds every kept PERMISSION_* key from helper/Permission/PermissionList.php.
     * `allowed_roles` stores English role-name strings; RolePermissionSeeder
     * turns them into role_permissions rows afterwards. Registrar gets most
     * user-management permissions, Teacher gets the read-only ones.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('You need to create a user before running PermissionSeeder.');
            return;
        }

        $superAdminRole = SUPER_ADMIN_ROLE_NAME;
        $registrarRole = 'Registrar';
        $teacherRole = 'Teacher';
        $headRole = 'Department Head';

        $permissionGroups = [
            PERMISSION_GROUP_SYSTEM_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_SYSTEM_MANAGEMENT)->first(),
            PERMISSION_GROUP_USER_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_USER_MANAGEMENT)->first(),
            PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT)->first(),
            PERMISSION_GROUP_PROFILE_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_PROFILE_MANAGEMENT)->first(),
            PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT)->first(),
            PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT)->first(),
            PERMISSION_GROUP_MASTER_DATA_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_MASTER_DATA_MANAGEMENT)->first(),
            PERMISSION_GROUP_OFFERING_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_OFFERING_MANAGEMENT)->first(),
            PERMISSION_GROUP_REPORT_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_REPORT_MANAGEMENT)->first(),
            PERMISSION_GROUP_NOTIFICATION_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_NOTIFICATION_MANAGEMENT)->first(),
        ];

        $missingGroups = array_keys(array_filter($permissionGroups, fn ($group) => $group === null));
        if (!empty($missingGroups)) {
            consoleError('PermissionSeeder cannot proceed: missing permission group(s) ' . implode(', ', $missingGroups));
            return;
        }

        $permissionToGroupMapping = [
            PERMISSION_GROUP_USER_MANAGEMENT => [
                PERMISSION_SEE_DASHBOARD,
                PERMISSION_SEE_USER,
                PERMISSION_CREATE_USER,
                PERMISSION_UPDATE_USER,
                PERMISSION_DELETE_USER,
                PERMISSION_CHANGE_USER_STATUS,
                PERMISSION_CHANGE_USER_STATE,
                PERMISSION_ASSIGN_ROLE_TO_USER,
                PERMISSION_ASSIGN_PERMISSION_TO_USER,
                PERMISSION_SEE_NOT_ASSIGNED_USERS,
                PERMISSION_MANAGE_USER_SESSIONS,
            ],
            PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT => [
                PERMISSION_SEE_PERMISSION_GROUP,
                PERMISSION_CREATE_PERMISSION_GROUP,
                PERMISSION_UPDATE_PERMISSION_GROUP,
                PERMISSION_DELETE_PERMISSION_GROUP,
                PERMISSION_SEE_PERMISSION,
                PERMISSION_CREATE_PERMISSION,
                PERMISSION_UPDATE_PERMISSION,
                PERMISSION_DELETE_PERMISSION,
                PERMISSION_CHANGE_PERMISSION_STATUS,
                PERMISSION_SEE_ROLE,
                PERMISSION_CREATE_ROLE,
                PERMISSION_UPDATE_ROLE,
                PERMISSION_DELETE_ROLE,
                PERMISSION_CHANGE_ROLE_TYPE,
                PERMISSION_CHANGE_ROLE_STATE,
                PERMISSION_CHANGE_ROLE_STATUS,
                PERMISSION_EDIT_USER_ROLE_BINDING,
                PERMISSION_REMOVE_ROLE_FROM_USER,
                PERMISSION_ADD_ROLE_PERMISSION,
                PERMISSION_SEE_ROLE_PERMISSION,
                PERMISSION_REMOVE_ROLE_PERMISSION,
            ],
            PERMISSION_GROUP_PROFILE_MANAGEMENT => [
                PERMISSION_SEE_PROFILE,
                PERMISSION_UPDATE_PROFILE,
            ],
            PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT => [
                PERMISSION_SEE_DYNAMIC_VALUE,
                PERMISSION_CREATE_DYNAMIC_VALUE,
                PERMISSION_UPDATE_DYNAMIC_VALUE,
                PERMISSION_DELETE_DYNAMIC_VALUE,
                PERMISSION_CHANGE_DYNAMIC_VALUE_STATUS,
                PERMISSION_CHANGE_DYNAMIC_VALUE_STATE,
                PERMISSION_ENTITY_ADD_DYNAMIC_VALUE,
            ],
            PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT => [
                PERMISSION_SEE_CLASS_SCHEDULE,
                PERMISSION_CREATE_CLASS_SCHEDULE,
                PERMISSION_UPDATE_CLASS_SCHEDULE,
                PERMISSION_DELETE_CLASS_SCHEDULE,
                PERMISSION_PUBLISH_CLASS_SCHEDULE,
                PERMISSION_CANCEL_CLASS_SCHEDULE,
                PERMISSION_RUN_CLASS_SCHEDULE_GENERATION,
                PERMISSION_RUN_EXAM_SCHEDULE_GENERATION,
                PERMISSION_SEE_SCHEDULE_GENERATION_RUN,
                PERMISSION_SEE_SCHEDULE_SETTING,
                PERMISSION_CREATE_SCHEDULE_SETTING,
                PERMISSION_UPDATE_SCHEDULE_SETTING,
                PERMISSION_SEE_EXAM_SCHEDULE,
                PERMISSION_CREATE_EXAM_SCHEDULE,
                PERMISSION_UPDATE_EXAM_SCHEDULE,
                PERMISSION_DELETE_EXAM_SCHEDULE,
                PERMISSION_CONFIRM_EXAM_SCHEDULE,
                PERMISSION_CONFIRM_CLASS_SCHEDULE,
                PERMISSION_PUBLISH_EXAM_SCHEDULE,
                PERMISSION_CANCEL_EXAM_SCHEDULE,
                PERMISSION_SEE_INVIGILATION_REQUEST,
                PERMISSION_CREATE_INVIGILATION_REQUEST,
                PERMISSION_UPDATE_INVIGILATION_REQUEST,
                PERMISSION_SEND_INVIGILATION_REQUEST,
                PERMISSION_RESPOND_TO_INVIGILATION_REQUEST,
                PERMISSION_SEE_INVIGILATOR_ASSIGNMENT,
                PERMISSION_ASSIGN_INVIGILATOR,
                PERMISSION_RESPOND_TO_INVIGILATOR_ASSIGNMENT,
                PERMISSION_REPLACE_INVIGILATOR,
            ],
            PERMISSION_GROUP_MASTER_DATA_MANAGEMENT => [
                PERMISSION_EXPORT_COLLEGE,
                PERMISSION_IMPORT_COLLEGE,
                PERMISSION_EXPORT_BUILDING,
                PERMISSION_IMPORT_BUILDING,
                PERMISSION_EXPORT_DEPARTMENT,
                PERMISSION_IMPORT_DEPARTMENT,
                PERMISSION_EXPORT_PROGRAM,
                PERMISSION_IMPORT_PROGRAM,
                PERMISSION_EXPORT_SECTION,
                PERMISSION_IMPORT_SECTION,
                PERMISSION_EXPORT_ROOM,
                PERMISSION_IMPORT_ROOM,
                PERMISSION_EXPORT_COURSE,
                PERMISSION_IMPORT_COURSE,
                PERMISSION_EXPORT_INSTRUCTOR,
                PERMISSION_IMPORT_INSTRUCTOR,
                PERMISSION_SEE_CAMPUS,
                PERMISSION_CREATE_CAMPUS,
                PERMISSION_UPDATE_CAMPUS,
                PERMISSION_DELETE_CAMPUS,
                PERMISSION_CHANGE_CAMPUS_STATE,
                PERMISSION_SEE_BUILDING,
                PERMISSION_CREATE_BUILDING,
                PERMISSION_UPDATE_BUILDING,
                PERMISSION_DELETE_BUILDING,
                PERMISSION_CHANGE_BUILDING_STATE,
                PERMISSION_SEE_COLLEGE,
                PERMISSION_CREATE_COLLEGE,
                PERMISSION_UPDATE_COLLEGE,
                PERMISSION_DELETE_COLLEGE,
                PERMISSION_CHANGE_COLLEGE_STATE,
                PERMISSION_SEE_DEPARTMENT,
                PERMISSION_CREATE_DEPARTMENT,
                PERMISSION_UPDATE_DEPARTMENT,
                PERMISSION_DELETE_DEPARTMENT,
                PERMISSION_CHANGE_DEPARTMENT_STATE,
                PERMISSION_SEE_ACADEMIC_YEAR,
                PERMISSION_CREATE_ACADEMIC_YEAR,
                PERMISSION_UPDATE_ACADEMIC_YEAR,
                PERMISSION_DELETE_ACADEMIC_YEAR,
                PERMISSION_SEE_PROGRAM,
                PERMISSION_CREATE_PROGRAM,
                PERMISSION_UPDATE_PROGRAM,
                PERMISSION_DELETE_PROGRAM,
                PERMISSION_CHANGE_PROGRAM_STATE,
                PERMISSION_SEE_SEMESTER,
                PERMISSION_CREATE_SEMESTER,
                PERMISSION_UPDATE_SEMESTER,
                PERMISSION_DELETE_SEMESTER,
                PERMISSION_CHANGE_SEMESTER_STATUS,
                PERMISSION_SEE_INSTRUCTOR,
                PERMISSION_CREATE_INSTRUCTOR,
                PERMISSION_UPDATE_INSTRUCTOR,
                PERMISSION_DELETE_INSTRUCTOR,
                PERMISSION_CHANGE_INSTRUCTOR_STATE,
                PERMISSION_SEE_SECTION,
                PERMISSION_CREATE_SECTION,
                PERMISSION_UPDATE_SECTION,
                PERMISSION_DELETE_SECTION,
                PERMISSION_CHANGE_SECTION_STATE,
                PERMISSION_SEE_COURSE,
                PERMISSION_CREATE_COURSE,
                PERMISSION_UPDATE_COURSE,
                PERMISSION_DELETE_COURSE,
                PERMISSION_CHANGE_COURSE_STATE,
                PERMISSION_SEE_ROOM,
                PERMISSION_CREATE_ROOM,
                PERMISSION_UPDATE_ROOM,
                PERMISSION_DELETE_ROOM,
                PERMISSION_CHANGE_ROOM_STATE,
            ],
            PERMISSION_GROUP_OFFERING_MANAGEMENT => [
                PERMISSION_SEE_COURSE_OFFERING,
                PERMISSION_CREATE_COURSE_OFFERING,
                PERMISSION_UPDATE_COURSE_OFFERING,
                PERMISSION_DELETE_COURSE_OFFERING,
                PERMISSION_SUBMIT_COURSE_OFFERING,
                PERMISSION_APPROVE_COURSE_OFFERING,
                PERMISSION_REJECT_COURSE_OFFERING,
            ],
            PERMISSION_GROUP_REPORT_MANAGEMENT => [
                PERMISSION_SEE_REPORT,
                PERMISSION_EXPORT_REPORT,
            ],
            PERMISSION_GROUP_NOTIFICATION_MANAGEMENT => [
                PERMISSION_SEE_NOTIFICATION,
                PERMISSION_CHANGE_NOTIFICATION_STATUS,
            ],
        ];

        $permissions = [
            ['name' => 'See Dashboard', 'key' => PERMISSION_SEE_DASHBOARD, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole, $teacherRole]],

            // user management
            ['name' => 'See User', 'key' => PERMISSION_SEE_USER, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create User', 'key' => PERMISSION_CREATE_USER, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update User', 'key' => PERMISSION_UPDATE_USER, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete User', 'key' => PERMISSION_DELETE_USER, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change User Status', 'key' => PERMISSION_CHANGE_USER_STATUS, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change User State', 'key' => PERMISSION_CHANGE_USER_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Assign Role To User', 'key' => PERMISSION_ASSIGN_ROLE_TO_USER, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Assign Permission To User', 'key' => PERMISSION_ASSIGN_PERMISSION_TO_USER, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'See Not Assigned Users', 'key' => PERMISSION_SEE_NOT_ASSIGNED_USERS, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Manage User Sessions', 'key' => PERMISSION_MANAGE_USER_SESSIONS, 'allowed_roles' => [$superAdminRole]],

            // permission group management
            ['name' => 'See Permission Group', 'key' => PERMISSION_SEE_PERMISSION_GROUP, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Create Permission Group', 'key' => PERMISSION_CREATE_PERMISSION_GROUP, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Update Permission Group', 'key' => PERMISSION_UPDATE_PERMISSION_GROUP, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Delete Permission Group', 'key' => PERMISSION_DELETE_PERMISSION_GROUP, 'allowed_roles' => [$superAdminRole]],

            // permission management
            ['name' => 'See Permission', 'key' => PERMISSION_SEE_PERMISSION, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Create Permission', 'key' => PERMISSION_CREATE_PERMISSION, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Update Permission', 'key' => PERMISSION_UPDATE_PERMISSION, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Delete Permission', 'key' => PERMISSION_DELETE_PERMISSION, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Permission Status', 'key' => PERMISSION_CHANGE_PERMISSION_STATUS, 'allowed_roles' => [$superAdminRole]],

            // role management
            ['name' => 'See Role', 'key' => PERMISSION_SEE_ROLE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Create Role', 'key' => PERMISSION_CREATE_ROLE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Update Role', 'key' => PERMISSION_UPDATE_ROLE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Delete Role', 'key' => PERMISSION_DELETE_ROLE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Role Type', 'key' => PERMISSION_CHANGE_ROLE_TYPE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Role State', 'key' => PERMISSION_CHANGE_ROLE_STATE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Role Status', 'key' => PERMISSION_CHANGE_ROLE_STATUS, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Edit User Role Binding', 'key' => PERMISSION_EDIT_USER_ROLE_BINDING, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Remove Role From User', 'key' => PERMISSION_REMOVE_ROLE_FROM_USER, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Add Role Permission', 'key' => PERMISSION_ADD_ROLE_PERMISSION, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'See Role Permission', 'key' => PERMISSION_SEE_ROLE_PERMISSION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Remove Role Permission', 'key' => PERMISSION_REMOVE_ROLE_PERMISSION, 'allowed_roles' => [$superAdminRole]],

            // profile management
            ['name' => 'See Profile', 'key' => PERMISSION_SEE_PROFILE, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Update Profile', 'key' => PERMISSION_UPDATE_PROFILE, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],

            // dynamic value management
            ['name' => 'See Dynamic Value', 'key' => PERMISSION_SEE_DYNAMIC_VALUE, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Dynamic Value', 'key' => PERMISSION_CREATE_DYNAMIC_VALUE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Update Dynamic Value', 'key' => PERMISSION_UPDATE_DYNAMIC_VALUE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Delete Dynamic Value', 'key' => PERMISSION_DELETE_DYNAMIC_VALUE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Dynamic Value Status', 'key' => PERMISSION_CHANGE_DYNAMIC_VALUE_STATUS, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Dynamic Value State', 'key' => PERMISSION_CHANGE_DYNAMIC_VALUE_STATE, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Entity Add Dynamic Value', 'key' => PERMISSION_ENTITY_ADD_DYNAMIC_VALUE, 'allowed_roles' => [$superAdminRole]],

            // class schedule management
            ['name' => 'See Class Schedule', 'key' => PERMISSION_SEE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole, $teacherRole]],
            ['name' => 'Create Class Schedule', 'key' => PERMISSION_CREATE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Class Schedule', 'key' => PERMISSION_UPDATE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Class Schedule', 'key' => PERMISSION_DELETE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Publish Class Schedule', 'key' => PERMISSION_PUBLISH_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Cancel Class Schedule', 'key' => PERMISSION_CANCEL_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Run Class Schedule Generation', 'key' => PERMISSION_RUN_CLASS_SCHEDULE_GENERATION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            // Configuration, not day-to-day scheduling: the registrar owns
            // the grid, a department head only reads it.
            ['name' => 'See Schedule Setting', 'key' => PERMISSION_SEE_SCHEDULE_SETTING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Create Schedule Setting', 'key' => PERMISSION_CREATE_SCHEDULE_SETTING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Schedule Setting', 'key' => PERMISSION_UPDATE_SCHEDULE_SETTING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Run Exam Schedule Generation', 'key' => PERMISSION_RUN_EXAM_SCHEDULE_GENERATION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'See Schedule Generation Run', 'key' => PERMISSION_SEE_SCHEDULE_GENERATION_RUN, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // exam schedule management
            ['name' => 'See Exam Schedule', 'key' => PERMISSION_SEE_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole, $teacherRole]],
            ['name' => 'Create Exam Schedule', 'key' => PERMISSION_CREATE_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Exam Schedule', 'key' => PERMISSION_UPDATE_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Exam Schedule', 'key' => PERMISSION_DELETE_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Confirm Exam Schedule', 'key' => PERMISSION_CONFIRM_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole]],
            ['name' => 'Confirm Class Schedule', 'key' => PERMISSION_CONFIRM_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole]],
            ['name' => 'Publish Exam Schedule', 'key' => PERMISSION_PUBLISH_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Cancel Exam Schedule', 'key' => PERMISSION_CANCEL_EXAM_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // invigilation — the department offers windows, the registrar reads them
            ['name' => 'See Invigilator Assignment', 'key' => PERMISSION_SEE_INVIGILATOR_ASSIGNMENT, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            // The registrar raises and issues the ask; the department answers it.
            ['name' => 'See Invigilation Request', 'key' => PERMISSION_SEE_INVIGILATION_REQUEST, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole, $teacherRole]],
            ['name' => 'Create Invigilation Request', 'key' => PERMISSION_CREATE_INVIGILATION_REQUEST, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Invigilation Request', 'key' => PERMISSION_UPDATE_INVIGILATION_REQUEST, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Send Invigilation Request', 'key' => PERMISSION_SEND_INVIGILATION_REQUEST, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Respond To Invigilation Request', 'key' => PERMISSION_RESPOND_TO_INVIGILATION_REQUEST, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole]],
            ['name' => 'Assign Invigilator', 'key' => PERMISSION_ASSIGN_INVIGILATOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            // The instructor answers for themselves, so a teacher holds this one.
            ['name' => 'Respond To Invigilator Assignment', 'key' => PERMISSION_RESPOND_TO_INVIGILATOR_ASSIGNMENT, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Replace Invigilator', 'key' => PERMISSION_REPLACE_INVIGILATOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: campuses
            ['name' => 'See Campus', 'key' => PERMISSION_SEE_CAMPUS, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Campus', 'key' => PERMISSION_CREATE_CAMPUS, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Campus', 'key' => PERMISSION_UPDATE_CAMPUS, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Campus', 'key' => PERMISSION_DELETE_CAMPUS, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Campus State', 'key' => PERMISSION_CHANGE_CAMPUS_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: buildings
            ['name' => 'See Building', 'key' => PERMISSION_SEE_BUILDING, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Building', 'key' => PERMISSION_CREATE_BUILDING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Building', 'key' => PERMISSION_UPDATE_BUILDING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Building', 'key' => PERMISSION_DELETE_BUILDING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Building State', 'key' => PERMISSION_CHANGE_BUILDING_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: spreadsheet import / export.
            // Export goes to whoever may already read the list; import is
            // held to the registrar, since it rewrites the table wholesale.
            ['name' => 'Export College', 'key' => PERMISSION_EXPORT_COLLEGE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import College', 'key' => PERMISSION_IMPORT_COLLEGE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Building', 'key' => PERMISSION_EXPORT_BUILDING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Building', 'key' => PERMISSION_IMPORT_BUILDING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Department', 'key' => PERMISSION_EXPORT_DEPARTMENT, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Department', 'key' => PERMISSION_IMPORT_DEPARTMENT, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Program', 'key' => PERMISSION_EXPORT_PROGRAM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Program', 'key' => PERMISSION_IMPORT_PROGRAM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Section', 'key' => PERMISSION_EXPORT_SECTION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Section', 'key' => PERMISSION_IMPORT_SECTION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Room', 'key' => PERMISSION_EXPORT_ROOM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Room', 'key' => PERMISSION_IMPORT_ROOM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Course', 'key' => PERMISSION_EXPORT_COURSE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Course', 'key' => PERMISSION_IMPORT_COURSE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Export Instructor', 'key' => PERMISSION_EXPORT_INSTRUCTOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Import Instructor', 'key' => PERMISSION_IMPORT_INSTRUCTOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: colleges / schools
            ['name' => 'See College', 'key' => PERMISSION_SEE_COLLEGE, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create College', 'key' => PERMISSION_CREATE_COLLEGE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update College', 'key' => PERMISSION_UPDATE_COLLEGE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete College', 'key' => PERMISSION_DELETE_COLLEGE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change College State', 'key' => PERMISSION_CHANGE_COLLEGE_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: departments
            ['name' => 'See Department', 'key' => PERMISSION_SEE_DEPARTMENT, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Department', 'key' => PERMISSION_CREATE_DEPARTMENT, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Department', 'key' => PERMISSION_UPDATE_DEPARTMENT, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Department', 'key' => PERMISSION_DELETE_DEPARTMENT, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Department State', 'key' => PERMISSION_CHANGE_DEPARTMENT_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: academic years (no state permission -- no is_active column)
            ['name' => 'See Academic Year', 'key' => PERMISSION_SEE_ACADEMIC_YEAR, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Academic Year', 'key' => PERMISSION_CREATE_ACADEMIC_YEAR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Academic Year', 'key' => PERMISSION_UPDATE_ACADEMIC_YEAR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Academic Year', 'key' => PERMISSION_DELETE_ACADEMIC_YEAR, 'allowed_roles' => [$superAdminRole]],

            // master data: programs
            ['name' => 'See Program', 'key' => PERMISSION_SEE_PROGRAM, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Program', 'key' => PERMISSION_CREATE_PROGRAM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Program', 'key' => PERMISSION_UPDATE_PROGRAM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Program', 'key' => PERMISSION_DELETE_PROGRAM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Program State', 'key' => PERMISSION_CHANGE_PROGRAM_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: semesters (status moves through lookup_transitions, not a state flag)
            ['name' => 'See Semester', 'key' => PERMISSION_SEE_SEMESTER, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Semester', 'key' => PERMISSION_CREATE_SEMESTER, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Semester', 'key' => PERMISSION_UPDATE_SEMESTER, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Semester', 'key' => PERMISSION_DELETE_SEMESTER, 'allowed_roles' => [$superAdminRole]],
            ['name' => 'Change Semester Status', 'key' => PERMISSION_CHANGE_SEMESTER_STATUS, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: instructors
            ['name' => 'See Instructor', 'key' => PERMISSION_SEE_INSTRUCTOR, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Instructor', 'key' => PERMISSION_CREATE_INSTRUCTOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Instructor', 'key' => PERMISSION_UPDATE_INSTRUCTOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Instructor', 'key' => PERMISSION_DELETE_INSTRUCTOR, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Instructor State', 'key' => PERMISSION_CHANGE_INSTRUCTOR_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: sections (student cohorts)
            ['name' => 'See Section', 'key' => PERMISSION_SEE_SECTION, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Section', 'key' => PERMISSION_CREATE_SECTION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Section', 'key' => PERMISSION_UPDATE_SECTION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Section', 'key' => PERMISSION_DELETE_SECTION, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Section State', 'key' => PERMISSION_CHANGE_SECTION_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: courses
            ['name' => 'See Course', 'key' => PERMISSION_SEE_COURSE, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Course', 'key' => PERMISSION_CREATE_COURSE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Course', 'key' => PERMISSION_UPDATE_COURSE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Course', 'key' => PERMISSION_DELETE_COURSE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Course State', 'key' => PERMISSION_CHANGE_COURSE_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // master data: rooms
            ['name' => 'See Room', 'key' => PERMISSION_SEE_ROOM, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Room', 'key' => PERMISSION_CREATE_ROOM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Room', 'key' => PERMISSION_UPDATE_ROOM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Room', 'key' => PERMISSION_DELETE_ROOM, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Room State', 'key' => PERMISSION_CHANGE_ROOM_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // course offering workflow
            ['name' => 'See Course Offering', 'key' => PERMISSION_SEE_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole, $teacherRole]],
            ['name' => 'Create Course Offering', 'key' => PERMISSION_CREATE_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Update Course Offering', 'key' => PERMISSION_UPDATE_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Delete Course Offering', 'key' => PERMISSION_DELETE_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Submit Course Offering', 'key' => PERMISSION_SUBMIT_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Approve Course Offering', 'key' => PERMISSION_APPROVE_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole]],
            ['name' => 'Reject Course Offering', 'key' => PERMISSION_REJECT_COURSE_OFFERING, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // report management
            ['name' => 'See Report', 'key' => PERMISSION_SEE_REPORT, 'allowed_roles' => [$superAdminRole, $registrarRole, $headRole, $teacherRole]],
            ['name' => 'Export Report', 'key' => PERMISSION_EXPORT_REPORT, 'allowed_roles' => [$superAdminRole, $registrarRole]],

            // notification management
            ['name' => 'See Notification', 'key' => PERMISSION_SEE_NOTIFICATION, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Change Notification Status', 'key' => PERMISSION_CHANGE_NOTIFICATION_STATUS, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($permissions as $info) {
                // Find which group this permission belongs to
                $groupCode = PERMISSION_GROUP_SYSTEM_MANAGEMENT; // Default fallback

                foreach ($permissionToGroupMapping as $code => $permissionKeys) {
                    if (in_array($info['key'], $permissionKeys)) {
                        $groupCode = $code;
                        break;
                    }
                }

                $permissionGroup = $permissionGroups[$groupCode];

                Permission::updateOrCreate(
                    ['key' => $info['key']],
                    [
                        'name' => [
                            English::getKey() => $info['name'],
                            Amharic::getKey() => AmharicNameComposer::compose($info['name']),
                        ],
                        'user_id' => $user->id,
                        'permission_group_id' => $permissionGroup->id,
                        'unique_per_user' => $info['unique_per_user'] ?? false,
                        'is_system' => true,
                        'state' => STATE_ACTIVE,
                        'allowed_roles' => $info['allowed_roles'] ?? [],
                    ]
                );
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            echo "Unable to seed permissions: " . $exception->getMessage();
        }
    }
}
