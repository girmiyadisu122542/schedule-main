<?php

namespace Database\Seeders\Physical;

use App\Models\Physical\Building;
use App\Models\Physical\Room;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Constants\AppConstant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Translation\Back\Amharic;
use Translation\Back\English;

class RoomSeeder extends Seeder {

    /**
     * Seed bookable venues. Building and ROOM_TYPE FKs both resolve by stable
     * code. Exam venues carry the spaced-seating `exam_capacity`; teaching-only
     * rooms leave it null so they stay out of the exam-venue partial index.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('RoomSeeder cannot proceed: no user found.');
            return;
        }

        $buildingByCode = fn (string $code): ?int => Building::query()->where('code', $code)->value('id');
        $roomTypeByCode = fn (string $code): ?int => LookupService::getValueByCode(ROOM_TYPE, $code, needId: true);

        if (!$buildingByCode('NB')) {
            consoleError('RoomSeeder cannot proceed: run BuildingSeeder first.');
            return;
        }

        if (!$roomTypeByCode(ROOM_TYPE_LECTURE_HALL)) {
            consoleError('RoomSeeder cannot proceed: ROOM_TYPE lookup values are missing.');
            return;
        }

        $rooms = [
            ['code' => 'NB-301', 'building' => 'NB', 'type' => ROOM_TYPE_LECTURE_HALL, 'floor' => 3, 'capacity' => 60, 'exam_capacity' => 30, 'is_exam_venue' => true, 'name' => ['en' => 'Lecture Hall 301', 'am' => 'የንባብ አዳራሽ 301']],
            ['code' => 'NB-302', 'building' => 'NB', 'type' => ROOM_TYPE_LECTURE_HALL, 'floor' => 3, 'capacity' => 60, 'exam_capacity' => null, 'is_exam_venue' => false, 'name' => ['en' => 'Lecture Hall 302', 'am' => 'የንባብ አዳራሽ 302']],
            ['code' => 'LAB-101', 'building' => 'LAB', 'type' => ROOM_TYPE_LAB, 'floor' => 1, 'capacity' => 30, 'exam_capacity' => null, 'is_exam_venue' => false, 'name' => ['en' => 'Computer Lab 1', 'am' => 'የኮምፒውተር ላብ 1']],
            ['code' => 'LAB-102', 'building' => 'LAB', 'type' => ROOM_TYPE_LAB, 'floor' => 1, 'capacity' => 30, 'exam_capacity' => null, 'is_exam_venue' => false, 'name' => ['en' => 'Computer Lab 2', 'am' => 'የኮምፒውተር ላብ 2']],
            ['code' => 'AB-AUD', 'building' => 'AB', 'type' => ROOM_TYPE_AUDITORIUM, 'floor' => 1, 'capacity' => 250, 'exam_capacity' => 120, 'is_exam_venue' => true, 'name' => ['en' => 'Main Auditorium', 'am' => 'ዋና ኦዲቶሪየም']],
            ['code' => 'AB-EX1', 'building' => 'AB', 'type' => ROOM_TYPE_EXAM_HALL, 'floor' => 2, 'capacity' => 120, 'exam_capacity' => 60, 'is_exam_venue' => true, 'name' => ['en' => 'Exam Hall 1', 'am' => 'የፈተና አዳራሽ 1']],
        ];

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($rooms as $room) {
                $row = Room::firstOrNew(['code' => $room['code']]);
                $row->fill([
                    'name' => [
                        English::getKey() => $room['name']['en'],
                        Amharic::getKey() => $room['name']['am'],
                    ],
                    'building_id' => $buildingByCode($room['building']),
                    'room_type_lookup_value_id' => $roomTypeByCode($room['type']),
                    'floor' => $room['floor'],
                    'capacity' => $room['capacity'],
                    'exam_capacity' => $room['exam_capacity'],
                    'is_exam_venue' => $room['is_exam_venue'],
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);
                $row->uuid ??= (string) Str::uuid();
                $row->save();
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            consoleError('Unable to seed rooms: ' . $exception->getMessage());
        }
    }
}
