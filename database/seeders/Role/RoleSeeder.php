<?php

namespace Database\Seeders\Role;

use App\Models\Role\Role;
use App\Models\User;
use Constants\AppConstant;
use Exception;
use Helper\Cache\PermissionCacheHandler;
use Helper\Translation\AmharicNameComposer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class RoleSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * Seeds the Super Admin system role (User::isSuperAdmin depends on the
     * SUPER_ADMIN_ROLE_NAME + is_system pair) plus two sample roles for the
     * starter kit: Registrar and Teacher.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('You need to create a user before running RoleSeeder.');
            return;
        }

        $roles = [
            ['name' => SUPER_ADMIN_ROLE_NAME, 'is_system' => true],
            ['name' => 'Registrar', 'is_system' => false],
            ['name' => 'Teacher', 'is_system' => false],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($roles as $info) {
                $role = Role::query()
                    ->where('name->' . English::getKey(), $info['name'])
                    ->first() ?? new Role();

                $role->uuid = $role->uuid ?? (string) Str::uuid();
                $role->name = [
                    English::getKey() => $info['name'],
                    Amharic::getKey() => AmharicNameComposer::compose($info['name']),
                ];
                $role->user_id = $user->id;
                $role->is_system = $info['is_system'];
                $role->unique_per_user = $info['unique_per_user'] ?? false;
                $role->state = STATE_ACTIVE;
                $role->save();
            }

            PermissionCacheHandler::updateRoleCache();
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            echo "Unable to seed roles: " . $exception->getMessage();
        }
    }
}
