<?php

namespace Database\Seeders\Permission;

use App\Models\Permission\PermissionGroup;
use App\Models\User;
use Constants\AppConstant;
use Exception;
use Helper\Translation\AmharicNameComposer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Translation\Back\Amharic;
use Translation\Back\English;

class PermissionGroupSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * Every entry ships level, is_group and parent_code explicitly -- a
     * missing key rolls back the whole transaction and breaks the tree.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('You need to create a user before running PermissionGroupSeeder.');
            return;
        }

        $permissionGroupsStructure = [
            [
                'name' => 'System Management',
                'code' => PERMISSION_GROUP_SYSTEM_MANAGEMENT,
                'level' => PERMISSION_GROUP_DEFAULT_LEVEL,
                'is_group' => true,
                'parent_code' => null,
            ],
            [
                'name' => 'User Management',
                'code' => PERMISSION_GROUP_USER_MANAGEMENT,
                'level' => PERMISSION_GROUP_SUB_LEVEL,
                'is_group' => false,
                'parent_code' => PERMISSION_GROUP_SYSTEM_MANAGEMENT,
            ],
            [
                'name' => 'Role & Permission Management',
                'code' => PERMISSION_GROUP_ROLE_PERMISSION_MANAGEMENT,
                'level' => PERMISSION_GROUP_SUB_LEVEL,
                'is_group' => false,
                'parent_code' => PERMISSION_GROUP_SYSTEM_MANAGEMENT,
            ],
            [
                'name' => 'Profile Management',
                'code' => PERMISSION_GROUP_PROFILE_MANAGEMENT,
                'level' => PERMISSION_GROUP_SUB_LEVEL,
                'is_group' => false,
                'parent_code' => null,
            ],
            [
                'name' => 'Dynamic Value Management',
                'code' => PERMISSION_GROUP_DYNAMIC_VALUE_MANAGEMENT,
                'level' => PERMISSION_GROUP_SUB_LEVEL,
                'is_group' => false,
                'parent_code' => PERMISSION_GROUP_SYSTEM_MANAGEMENT,
            ],
            [
                'name' => 'Class Schedule Management',
                'code' => PERMISSION_GROUP_CLASS_SCHEDULE_MANAGEMENT,
                'level' => PERMISSION_GROUP_DEFAULT_LEVEL,
                'is_group' => false,
                'parent_code' => null,
            ],
            [
                'name' => 'Master Data Management',
                'code' => PERMISSION_GROUP_MASTER_DATA_MANAGEMENT,
                'level' => PERMISSION_GROUP_DEFAULT_LEVEL,
                'is_group' => false,
                'parent_code' => null,
            ],
            [
                'name' => 'Course Offering Management',
                'code' => PERMISSION_GROUP_OFFERING_MANAGEMENT,
                'level' => PERMISSION_GROUP_DEFAULT_LEVEL,
                'is_group' => false,
                'parent_code' => null,
            ],
            [
                'name' => 'Report Management',
                'code' => PERMISSION_GROUP_REPORT_MANAGEMENT,
                'level' => PERMISSION_GROUP_DEFAULT_LEVEL,
                'is_group' => false,
                'parent_code' => null,
            ],
            [
                'name' => 'Notification Management',
                'code' => PERMISSION_GROUP_NOTIFICATION_MANAGEMENT,
                'level' => PERMISSION_GROUP_DEFAULT_LEVEL,
                'is_group' => false,
                'parent_code' => null,
            ],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $createdGroups = [];

            foreach ($permissionGroupsStructure as $groupInfo) {
                $permissionGroup = PermissionGroup::updateOrCreate(
                    ['code' => $groupInfo['code']],
                    [
                        'name' => [
                            English::getKey() => $groupInfo['name'],
                            Amharic::getKey() => AmharicNameComposer::compose($groupInfo['name']),
                        ],
                        'level' => $groupInfo['level'] ?? PERMISSION_GROUP_DEFAULT_LEVEL,
                        'state' => STATE_ACTIVE,
                        'user_id' => $user->id,
                        'is_group' => $groupInfo['is_group'] ?? false,
                    ]
                );

                $createdGroups[$groupInfo['code']] = $permissionGroup;
            }

            foreach ($permissionGroupsStructure as $groupInfo) {
                if (!empty($groupInfo['parent_code'])) {
                    $childGroup = $createdGroups[$groupInfo['code']];
                    $parentGroup = $createdGroups[$groupInfo['parent_code']];

                    $childGroup->permission_group_id = $parentGroup->id;
                    $childGroup->save();
                }
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            echo "Unable to seed permission groups: " . $exception->getMessage();
        }
    }
}
