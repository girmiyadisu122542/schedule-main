<?php

namespace Database\Seeders\Academic;

use App\Models\Academic\College;
use App\Models\User;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class CollegeSeeder extends Seeder {

    /**
     * Seed the institution's colleges. `dean_user_id` is left null — it is a
     * routing pointer a registrar fills in later, not part of the fixture.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('CollegeSeeder cannot proceed: no user found.');
            return;
        }

        $colleges = [
            ['code' => 'COET', 'name' => ['en' => 'College of Engineering and Technology', 'am' => 'የምህንድስና እና ቴክኖሎጂ ኮሌጅ']],
            ['code' => 'CNCS', 'name' => ['en' => 'College of Natural and Computational Sciences', 'am' => 'የተፈጥሮ እና ስሌት ሳይንስ ኮሌጅ']],
            ['code' => 'CBE', 'name' => ['en' => 'College of Business and Economics', 'am' => 'የቢዝነስ እና ኢኮኖሚክስ ኮሌጅ']],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($colleges as $college) {
                // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
                $row = College::firstOrNew(['code' => $college['code']]);
                $row->fill([
                    'name' => [
                        English::getKey() => $college['name']['en'],
                        Amharic::getKey() => $college['name']['am'],
                    ],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed colleges: ' . $exception->getMessage());
        }
    }
}
