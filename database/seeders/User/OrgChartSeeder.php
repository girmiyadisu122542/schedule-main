<?php

namespace Database\Seeders\User;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\People\Instructor;
use App\Models\Role\Role;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use Carbon\Carbon;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class OrgChartSeeder extends Seeder {

    /**
     * Populate the org chart the department scope is DERIVED from.
     *
     * `DepartmentScopeService` answers "whose offerings may this user act on?"
     * by reading `departments.head_user_id`, `colleges.dean_user_id` and
     * `instructors.user_id`. Those three columns were never seeded — every
     * department had a null head and every college a null dean — so the service
     * resolved almost everyone to "no departments at all". The scope was
     * enforced correctly and bound nobody, which is indistinguishable from
     * broken on screen.
     *
     * This seeds the cast the approval chain needs to be exercised end to end:
     * a Committee Leader who authors, a Department Head, and a College Dean.
     * The Registrar and Super Admin already exist and are unrestricted through
     * `see:all:departments`.
     *
     * @return void
     */
    public function run(): void {
        $admin = User::query()->where('email', 'admin@schedule.com')->first();
        if (!$admin) {
            consoleError('OrgChartSeeder cannot proceed: run UserSeeder first.');
            return;
        }

        $computerScience = Department::query()->where('code', 'CS')->first();
        if (!$computerScience) {
            consoleError('OrgChartSeeder cannot proceed: run CollegeSeeder and DepartmentSeeder first.');
            return;
        }

        // Resolved FROM the department rather than named directly. The dean has
        // to lead the college the seeded department actually sits under, or the
        // college tier can never act on it — CS is under CNCS, not the
        // engineering college its name suggests.
        $college = College::query()->whereKey($computerScience->college_id)->first();
        if (!$college) {
            consoleError('OrgChartSeeder cannot proceed: the CS department has no college.');
            return;
        }

        $userSeeder = new UserSeeder();
        $english = English::getKey();
        $amharic = Amharic::getKey();

        $cast = [
            ['email' => 'committee', 'role' => 'Committee Leader', 'phone' => '0955555555'],
            ['email' => 'head', 'role' => 'Department Head', 'phone' => '0966666666'],
            ['email' => 'dean', 'role' => 'College Dean', 'phone' => '0977777777'],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $created = [];

            foreach ($cast as $member) {
                $role = Role::query()->where('name->' . $english, $member['role'])->first();
                if (!$role) {
                    consoleError("OrgChartSeeder cannot proceed: role '{$member['role']}' is missing — run RoleSeeder first.");
                    DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

                    return;
                }

                $user = $userSeeder->createUserFrom([
                    'first_name' => $userSeeder->nameByGender(MALE, $english, $amharic),
                    'middle_name' => $userSeeder->nameByGender(MALE, $english, $amharic),
                    'last_name' => $userSeeder->nameByGender(MALE, $english, $amharic),
                    'email' => $member['email'],
                    'gender' => MALE,
                    'phone' => $member['phone'],
                ]);

                UserRoleBinding::updateOrCreate(
                    ['user_id' => $user->id, 'role_id' => $role->id],
                    ['assigned_by' => $admin->id, 'starts_at' => Carbon::now()->subHour()],
                );

                $created[$member['email']] = $user;
            }

            // The two routing pointers the scope is read from. A head covers one
            // department; a dean covers every department in their college, which
            // is what lets the college tier act without naming each one.
            $computerScience->head_user_id = $created['head']->id;
            $computerScience->save();

            $college->dean_user_id = $created['dean']->id;
            $college->save();

            // The third route into the scope: teaching in a department binds you
            // to it. The Committee Leader reaches CS this way and no other — they
            // are staff, not its head — so without an instructor row they resolve
            // to no departments at all and cannot author the very offerings the
            // role exists to write.
            $this->linkInstructor($created['committee'], $computerScience->id, 'EMP-CMT-1', 'Committee Leader');
            $this->linkInstructor($created['head'], $computerScience->id, 'EMP-HEAD-1', 'Department Head');

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed the org chart: ' . $exception->getMessage());
        }
    }

    /**
     * Give a user an instructor row, which is what binds them to a department.
     *
     * @param \App\Models\User $user
     * @param int $departmentId
     * @param string $employeeNo
     * @param string $label a human note for the seeded row's name
     *
     * @return void
     */
    private function linkInstructor(User $user, int $departmentId, string $employeeNo, string $label): void {
        $instructor = Instructor::firstOrNew(['employee_no' => $employeeNo]);

        $instructor->fill([
            'full_name' => [
                English::getKey() => $user->full_name[English::getKey()] ?? $label,
                Amharic::getKey() => $user->full_name[Amharic::getKey()] ?? $label,
            ],
            'email' => $user->email,
            'phone' => $user->phone,
            'department_id' => $departmentId,
            'user_id' => $user->id,
            'can_teach' => true,
            'can_invigilate' => true,
            'is_active' => true,
        ]);

        $instructor->uuid ??= (string) Str::uuid();
        $instructor->save();
    }
}
