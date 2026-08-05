<?php

namespace Database\Seeders\Academic;

use App\Models\Academic\AcademicYear;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicYearSeeder extends Seeder {

    /**
     * Seed two academic years — the current one and its predecessor. Exactly one
     * carries `is_current`, which the partial unique index enforces.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('AcademicYearSeeder cannot proceed: no user found.');
            return;
        }

        $academicYears = [
            ['code' => '2024/25', 'start_date' => '2024-09-09', 'end_date' => '2025-07-07', 'is_current' => false],
            ['code' => '2025/26', 'start_date' => '2025-09-08', 'end_date' => '2026-07-06', 'is_current' => true],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($academicYears as $academicYear) {
                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = AcademicYear::firstOrNew(['code' => $academicYear['code']]);
                $row->fill([
                    'start_date' => $academicYear['start_date'],
                    'end_date' => $academicYear['end_date'],
                    'is_current' => $academicYear['is_current'],
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed academic years: ' . $exception->getMessage());
        }
    }
}
