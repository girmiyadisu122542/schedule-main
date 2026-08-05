<?php

namespace Database\Seeders\Physical;

use App\Models\Physical\Building;
use App\Models\Physical\Campus;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class BuildingSeeder extends Seeder {

    /**
     * Seed buildings for the seeded campuses. Campus FKs resolve by the
     * stable campus `code`, never an auto-increment id.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('BuildingSeeder cannot proceed: no user found.');
            return;
        }

        $campusByCode = fn (string $code): ?int => Campus::query()->where('code', $code)->value('id');

        if (!$campusByCode('MAIN')) {
            consoleError('BuildingSeeder cannot proceed: run CampusSeeder first.');
            return;
        }

        $buildings = [
            [
                'code' => 'NB',
                'name' => ['en' => 'New Block', 'am' => 'አዲስ ብሎክ'],
                'campus_code' => 'MAIN',
                'floors' => 5,
            ],
            [
                'code' => 'AB',
                'name' => ['en' => 'Administration Block', 'am' => 'የአስተዳደር ብሎክ'],
                'campus_code' => 'MAIN',
                'floors' => 3,
            ],
            [
                'code' => 'LAB',
                'name' => ['en' => 'Laboratory Block', 'am' => 'የላብራቶሪ ብሎክ'],
                'campus_code' => 'TECH',
                'floors' => 4,
            ],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($buildings as $building) {
                // See CampusSeeder — WithoutModelEvents suppresses the uuid hook.
                $row = Building::firstOrNew(['code' => $building['code']]);
                $row->fill([
                    'name' => [
                        English::getKey() => $building['name']['en'],
                        Amharic::getKey() => $building['name']['am'],
                    ],
                    'campus_id' => $campusByCode($building['campus_code']),
                    'floors' => $building['floors'],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed buildings: ' . $exception->getMessage());
        }
    }
}
