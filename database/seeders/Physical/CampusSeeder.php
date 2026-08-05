<?php

namespace Database\Seeders\Physical;

use App\Models\Physical\Campus;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class CampusSeeder extends Seeder {

    /**
     * Seed the institution's campuses. Owned by the first (admin) user.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('CampusSeeder cannot proceed: no user found.');
            return;
        }

        $campuses = [
            [
                'code' => 'MAIN',
                'name' => ['en' => 'Main Campus', 'am' => 'ዋና ካምፓስ'],
                'address' => ['en' => 'Arat Kilo, Addis Ababa', 'am' => 'አራት ኪሎ፣ አዲስ አበባ'],
                'city' => 'Addis Ababa',
                'is_main' => true,
            ],
            [
                'code' => 'TECH',
                'name' => ['en' => 'Technology Campus', 'am' => 'የቴክኖሎጂ ካምፓስ'],
                'address' => ['en' => 'Amist Kilo, Addis Ababa', 'am' => 'አምስት ኪሎ፣ አዲስ አበባ'],
                'city' => 'Addis Ababa',
                'is_main' => false,
            ],
            [
                'code' => 'SOUTH',
                'name' => ['en' => 'Southern Campus', 'am' => 'ደቡብ ካምፓስ'],
                'address' => ['en' => 'Hawassa', 'am' => 'ሀዋሳ'],
                'city' => 'Hawassa',
                'is_main' => false,
            ],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($campuses as $campus) {
                // DatabaseSeeder runs WithoutModelEvents, so the uuid-filling
                // `creating` hook never fires here — stamp it by hand, and only
                // on insert so a re-seed keeps the public identifier stable.
                $row = Campus::firstOrNew(['code' => $campus['code']]);
                $row->fill([
                    'name' => [
                        English::getKey() => $campus['name']['en'],
                        Amharic::getKey() => $campus['name']['am'],
                    ],
                    'address' => [
                        English::getKey() => $campus['address']['en'],
                        Amharic::getKey() => $campus['address']['am'],
                    ],
                    'city' => $campus['city'],
                    'is_main' => $campus['is_main'],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed campuses: ' . $exception->getMessage());
        }
    }
}
