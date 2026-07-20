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
            'collegesOrSchools' => 'Colleges/Schools',
            'departments' => 'Departments',
            'instructors' => 'Instructors',
            'classes' => 'Classes',
            'courses' => 'Courses',
            'rooms' => 'Rooms',
            'scheduling' => 'Scheduling',
            'classSchedules' => 'Class Schedules',
            'examSchedules' => 'Exam Schedules',
            'reports' => 'Reports',
            'notifications' => 'Notifications',

            //subscription and pricing related items
        ];
    }
}
