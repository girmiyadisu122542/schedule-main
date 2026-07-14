<?php

namespace Database\Seeders\Role;

use App\Models\Permission\Permission;
use App\Models\Role\Role;
use App\Models\Role\RolePermission;
use App\Models\User;
use Constants\AppConstant;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Translation\Back\English;

class RolePermissionSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * Attaches permissions to roles based on each permission's
     * `allowed_roles` list (English role-name strings populated by
     * PermissionSeeder).
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();

        if (!$user) {
            consoleError('No user found. Please seed users first.');
            return;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            // Get all roles by their English name (allowed_roles stores English name strings)
            $roles = Role::all()->keyBy(fn ($role) => $role->name[English::getKey()] ?? null);

            // Get all permissions (they already have roles column populated by PermissionSeeder)
            $permissions = Permission::all();

            $assignedCount = 0;
            $skippedCount = 0;

            foreach ($permissions as $permission) {
                // Skip permissions that don't have any roles assigned
                if (!$permission->allowed_roles || empty($permission->allowed_roles)) {
                    $skippedCount++;
                    continue;
                }

                // For each role assigned to this permission
                foreach ($permission->allowed_roles as $roleName) {
                    if (!isset($roles[$roleName])) {
                        echo "Warning: Role '$roleName' not found for permission '{$permission->key}'. Skipping.\n";
                        $skippedCount++;
                        continue;
                    }

                    $role = $roles[$roleName];

                    RolePermission::updateOrCreate(
                        [
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                        ],
                        [
                            'user_id' => $user->id,
                            'state' => STATE_ACTIVE,
                        ]
                    );
                    $assignedCount++;
                }
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();

            echo "Role permissions seeded successfully!\n";
            echo "Statistics:\n";
            echo "- Permissions processed: " . $permissions->count() . "\n";
            echo "- Role-permission assignments made: " . $assignedCount . "\n";
            echo "- Assignments skipped: " . $skippedCount . "\n";

            // Show summary by role
            $this->showRoleSummary($roles);

        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            echo "Unable to seed role permissions: " . $exception->getMessage() . "\n";
        }
    }

    /**
     * Display a summary of permissions per role.
     *
     * @param \Illuminate\Support\Collection $roles
     *
     * @return void
     */
    private function showRoleSummary($roles): void {
        echo "\n Permissions per role summary:\n";
        echo str_repeat("-", 50) . "\n";

        foreach ($roles as $role) {
            $permissionCount = RolePermission::where('role_id', $role->id)->count();
            echo sprintf(
                "   %-15s: %3d permissions\n",
                $role->name__localized,
                $permissionCount
            );
        }

        echo str_repeat("-", 50) . "\n";

        // Verify that Super Admin has the most permissions
        $superAdmin = $roles[SUPER_ADMIN_ROLE_NAME] ?? null;
        if ($superAdmin) {
            $superAdminCount = RolePermission::where('role_id', $superAdmin->id)->count();
            $totalPermissions = Permission::count();
            $percentage = $totalPermissions > 0 ? round(($superAdminCount / $totalPermissions) * 100, 1) : 0;

            echo "Super Admin has {$superAdminCount}/{$totalPermissions} permissions ({$percentage}%)\n";

            if ($percentage < 95) {
                echo "Note: Super Admin might not have all system permissions. Check PermissionSeeder.\n";
            }
        }
    }
}
