<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Academic\AcademicYearSeeder;
use Database\Seeders\Academic\CollegeSeeder;
use Database\Seeders\Academic\DepartmentSeeder;
use Database\Seeders\Academic\ProgramSeeder;
use Database\Seeders\Academic\SectionSeeder;
use Database\Seeders\Academic\SemesterSeeder;
use Database\Seeders\Catalogue\CourseSeeder;
use Database\Seeders\Invigilation\ExamInvigilatorAssignmentSeeder;
use Database\Seeders\Lookup\LookupSeeder;
use Database\Seeders\Offering\CourseOfferingSeeder;
use Database\Seeders\People\InstructorSeeder;
use Database\Seeders\Permission\PermissionGroupSeeder;
use Database\Seeders\Permission\PermissionSeeder;
use Database\Seeders\Physical\BuildingSeeder;
use Database\Seeders\Physical\CampusSeeder;
use Database\Seeders\Physical\RoomSeeder;
use Database\Seeders\Role\RolePermissionSeeder;
use Database\Seeders\Role\RoleSeeder;
use Database\Seeders\Role\UserRoleBindingSeeder;
use Database\Seeders\Schedule\ClassScheduleSeeder;
use Database\Seeders\Schedule\ScheduleSettingSeeder;
use Database\Seeders\Schedule\ExamScheduleSeeder;
use Database\Seeders\User\UserSeeder;
use Helper\Cache\RoleCacheHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder {
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void {
        try {
            $this->call([
                UserSeeder::class,
                LookupSeeder::class,
                RoleSeeder::class,
                PermissionGroupSeeder::class,
                PermissionSeeder::class,
                UserRoleBindingSeeder::class,
                RolePermissionSeeder::class,
                CampusSeeder::class,
                BuildingSeeder::class,
                CollegeSeeder::class,
                DepartmentSeeder::class,
                AcademicYearSeeder::class,
                ProgramSeeder::class,
                SemesterSeeder::class,
                SectionSeeder::class,
                RoomSeeder::class,
                CourseSeeder::class,
                InstructorSeeder::class,
                CourseOfferingSeeder::class,
                ScheduleSettingSeeder::class,
                ClassScheduleSeeder::class,
                ExamScheduleSeeder::class,
                ExamInvigilatorAssignmentSeeder::class,
            ]);
        } catch (\Throwable $exception) {
            $this->command?->error('Seeding failed: ' . $exception->getMessage());
        }

        if (Schema::hasTable('users')) {
            RoleCacheHandler::updateUserCache(User::all());
        }
    }
}
