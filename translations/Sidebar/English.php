<?php

namespace Translation\Sidebar;

use Common\Lang\Lang;

class English extends Lang {

    protected static $key = 'en';
    protected static $name = 'english';
    protected static $icon = 'us.png';

    /**
     * The language translations
     *
     * @return array<string, string>
     */
    public static function translations(): array {
        return [
            'menu' => 'Menu',
            'dashboard' => 'Dashboard',

            // permissions sidebar items
            'accessManagement' => 'Access Management',
            'permissions' => 'Permissions',
            'createPermissionGroups' => 'Create Permission Groups',
            'permissionGroups' => "Permission Groups",
            'manageUsers' => 'User',
            'manageRoles' => 'Role',
            'managePermissions' => 'Permissions',
            'access' => 'Access',
            'userPermissionOverride' => 'User Permission Override',

            //role
            'roles' => 'Roles',
            'viewRoles' => 'View Roles',
            'createRole' => 'Create Role',
            'editRole' => 'Edit Role',
            'assignRole' => 'Assign Role',
            'commons' => 'Commons',
            // For Test
            'administrativeStructure' => 'Administrative Structure',

            // company setup related items
            'companySetup' => 'Tenant Setup',
            'systemSetup' => 'System Setup',
            'userAndAccess' => 'User And Access',
            'adminSetup' => 'Admin Setup',

            // dynamic configuration related items
            'dynamicConfiguration' => 'Dynamic Configuration',
            'dynamicValues' => 'Dynamic Values',
            'profile' => 'Profile',
            'userProfile' => 'User Profile',

            // measurement related items
            'measurement' => 'Measureemnt',

            // customer related items
            'crmSetup' => 'CRM Setup',
            'customer' => 'Customer',

            'supplier' => 'Supplier',
            'employee' => 'Employee',

            'configurations' => 'Configurations',
            'campuses' => 'Campuses',
            'buildings' => 'Buildings',
            'collegesOrSchools' => 'Colleges/Schools',
            'academicYears' => 'Academic Years',
            'programs' => 'Programs',
            'semesters' => 'Semesters',
            'departments' => 'Departments',
            'instructors' => 'Instructors',
            'sections' => 'Sections',
            'courses' => 'Courses',
            'rooms' => 'Rooms',
            'scheduleSettings' => 'Schedule Settings',
            'courseOfferings' => 'Course Offerings',
            'scheduling' => 'Scheduling',
            'classSchedules' => 'Class Schedules',
            'invigilation' => 'Invigilation',
            'invigilationRequests' => 'Invigilation Requests',
            'invigilatorAvailabilities' => 'Availability',
            'invigilatorAssignments' => 'Duty Roster',
            'timetable' => 'Timetable',
            'examCalendar' => 'Exam Calendar',
            'examSchedules' => 'Exam Schedules',
            'reports' => 'Reports',
            'notifications' => 'Notifications',

            //subscription and pricing related items
        ];
    }
}
