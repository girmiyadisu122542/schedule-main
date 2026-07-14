<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Lookup\LookupSeeder;
use Database\Seeders\Permission\PermissionGroupSeeder;
use Database\Seeders\Permission\PermissionSeeder;
use Database\Seeders\Role\RolePermissionSeeder;
use Database\Seeders\Role\RoleSeeder;
use Database\Seeders\Role\UserRoleBindingSeeder;
use Database\Seeders\Schedule\ClassScheduleSeeder;
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
                ClassScheduleSeeder::class,
            ]);
        } catch (\Throwable $exception) {
            $this->command?->error('Seeding failed: ' . $exception->getMessage());
        }

        if (Schema::hasTable('users')) {
            RoleCacheHandler::updateUserCache(User::all());
        }
    }
}
