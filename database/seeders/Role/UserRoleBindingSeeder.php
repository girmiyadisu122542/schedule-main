<?php

namespace Database\Seeders\Role;

use App\Helpers\AmharicFaker;
use App\Models\Role\Role;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use Carbon\Carbon;
use Constants\AppConstant;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Translation\Back\English;

class UserRoleBindingSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * Flat RBAC bindings: admin -> Super Admin, registrar -> Registrar,
     * teacher -> Teacher. The student sample user is intentionally left
     * unbound so the "not assigned users" screen has data.
     *
     * @return void
     */
    public function run(): void {
        $assignedBy = User::where('email', AmharicFaker::email('admin', true))->first();

        if (!$assignedBy) {
            consoleError('Admin user not found. Please seed users first.');
            return;
        }

        // Map email to role name
        $userRoleBindings = [
            ['email' => 'admin', 'role_name' => SUPER_ADMIN_ROLE_NAME],
            ['email' => 'registrar', 'role_name' => 'Registrar'],
            ['email' => 'teacher', 'role_name' => 'Teacher'],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            // Clear existing bindings
            UserRoleBinding::query()->delete();

            foreach ($userRoleBindings as $binding) {
                $email = AmharicFaker::email($binding['email'], true);
                $user = User::where('email', $email)->first();
                $role = Role::where('name->' . English::getKey(), $binding['role_name'])->first();

                if (!$user) {
                    echo "User '{$email}' not found, skipping...\n";
                    continue;
                }

                if (!$role) {
                    echo "Role '{$binding['role_name']}' not found, skipping...\n";
                    continue;
                }

                UserRoleBinding::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'assigned_by' => $assignedBy->id,
                    'starts_at' => Carbon::now()->subHour(1),
                ]);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            echo "Unable to seed user role binding: " . $exception->getMessage() . "\n";
        }
    }
}
