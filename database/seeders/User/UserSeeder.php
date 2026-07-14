<?php

namespace Database\Seeders\User;

use App\Helpers\AmharicFaker;
use App\Models\User;
use App\Models\User\UserDetail;
use Constants\AppConstant;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Translation\Front\Amharic;
use Translation\Front\English;

class UserSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * Seeds the system admin plus a few obvious sample users for the
     * starter kit (a registrar, a teacher and a student). Every seeded
     * account logs in with DEFAULT_USER_PASSWORD.
     *
     * @return void
     */
    public function run(): void {
        $english = English::getKey();
        $amharic = Amharic::getKey();

        $sampleEmails = [
            'admin' => '0911111111',
            'registrar' => '0922222222',
            'teacher' => '0933333333',
            'student' => '0944444444',
        ];

        $users = [];
        foreach ($sampleEmails as $email => $phone) {
            $users[] = [
                'first_name' => $this->nameByGender(MALE, $english, $amharic),
                'middle_name' => $this->nameByGender(MALE, $english, $amharic),
                'last_name' => $this->nameByGender(MALE, $english, $amharic),
                'email' => $email,
                'gender' => MALE,
                'phone' => $phone,
            ];
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($users as $user) {
                $this->createUserFrom($user);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            echo "Failed to seed users: " . $exception->getMessage() . "\n";
        }
    }

    /**
     * Create (or update) a user and its detail row from the given info.
     *
     * @param array $info
     * @param int|null $createdBy
     *
     * @return User
     */
    public function createUserFrom($info, $createdBy = null) {
        $email = AmharicFaker::email($info['email'], true);

        $userDetail = UserDetail::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $info['first_name'],
                'middle_name' => $info['middle_name'],
                'last_name' => $info['last_name'],
                'email' => $email,
                'phone' => $info['phone'],
                'gender' => $info['gender'],
                'birth_date' => now()->subYear(mt_rand(18, 40)),
                'national_id' => AmharicFaker::nationalId(),
            ]
        );

        $fullName = [
            English::getKey() => $info['first_name'][English::getKey()] . ' ' . $info['middle_name'][English::getKey()],
            Amharic::getKey() => $info['first_name'][Amharic::getKey()] . ' ' . $info['middle_name'][Amharic::getKey()],
        ];

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) Str::uuid(),
                'email' => $email,
                'full_name' => $fullName,
                'phone' => $info['phone'],
                'state' => USER_STATE_ACTIVE,
                'user_detail_id' => $userDetail->id,
            ]
        );

        $user->password = Hash::make(DEFAULT_USER_PASSWORD);
        $user->save();

        return $user;
    }

    /**
     * Build a jsonb name payload for the given gender.
     *
     * @param int $gender
     * @param string $english
     * @param string $amharic
     *
     * @return array
     */
    public function nameByGender($gender, $english, $amharic): array {
        $name = $gender === MALE ? AmharicFaker::maleName() : AmharicFaker::femaleName();

        return [
            $english => toEnglish($name),
            $amharic => $name,
        ];
    }
}
