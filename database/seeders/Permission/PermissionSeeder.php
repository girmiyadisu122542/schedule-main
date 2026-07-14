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

        $permissionGroups = [
            PERMISSION_GROUP_SYSTEM_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_SYSTEM_MANAGEMENT)->first(),
            PERMISSION_GROUP_USER_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_USER_MANAGEMENT)->first(),
            PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT)->first(),
            PERMISSION_GROUP_PROFILE_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_PROFILE_MANAGEMENT)->first(),
            PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT)->first(),
            PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT => PermissionGroup::where('code', PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT)->first(),
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
                PERMISSION_CHANGE_CLASS_SCHEDULE_STATE,
            ],
        ];

        $permissions = [
            ['name' => 'See Dashboard', 'key' => PERMISSION_SEE_DASHBOARD, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],

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

            // class / exam schedule management (sample feature)
            ['name' => 'See Class Schedule', 'key' => PERMISSION_SEE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole, $teacherRole]],
            ['name' => 'Create Class Schedule', 'key' => PERMISSION_CREATE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Update Class Schedule', 'key' => PERMISSION_UPDATE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Delete Class Schedule', 'key' => PERMISSION_DELETE_CLASS_SCHEDULE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
            ['name' => 'Change Class Schedule State', 'key' => PERMISSION_CHANGE_CLASS_SCHEDULE_STATE, 'allowed_roles' => [$superAdminRole, $registrarRole]],
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
