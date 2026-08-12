<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Physical\Building;
use App\Models\Physical\Room;

/**
 * Rooms — bookable venues for classes and exams (Final Schema.md §9).
 *
 * `capacity` and `exam_capacity` are deliberately separate: spaced exam seating
 * uses roughly half a hall's teaching capacity, so one number would either
 * overbook every exam or waste half of every classroom. `is_exam_venue` is a
 * use-flag independent of `room_type_code` — a large lecture hall may serve as
 * an exam venue, so eligibility is not derived from the type.
 */
class RoomColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'room';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Room::class;
    }

    /**
     * @return array<int, string>
     */
    public function naturalKey(): array {
        return ['code'];
    }

    /**
     * @return array<int, string>
     */
    public function exportWith(): array {
        return ['building', 'roomType'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('code')
                ->required()
                ->rules(['string', 'max:' . MAX_ROOM_CODE_LENGTH])
                ->example('NB-301'),

            // Nullable on this table — an unnamed room is identified by its code.
            Column::make('name')
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_NAME_LENGTH])
                ->example('Lecture Hall 301'),

            Column::make('building_code', 'building_id')
                ->required()
                ->resolvesTo(Building::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('NB')
                ->exportUsing(fn ($room) => $room->building?->code),

            Column::make('room_type_code', 'room_type_lookup_value_id')
                ->required()
                ->resolvesToLookup(ROOM_TYPE)
                ->rules(['string'])
                ->example(ROOM_TYPE_LECTURE_HALL)
                ->exportUsing(fn ($room) => $room->roomType?->code),

            // Signed, so basements are honest.
            Column::make('floor')
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:' . MIN_BUILDING_FLOORS . ',' . MAX_BUILDING_FLOORS])
                ->example(3),

            Column::make('capacity')
                ->required()
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:1,' . MAX_ROOM_CAPACITY])
                ->example(60),

            // Required once the venue flag is on: an exam venue with no
            // spaced-seating figure cannot be scheduled.
            Column::make('exam_capacity')
                ->type(Column::TYPE_INTEGER)
                ->rules(['required_if:is_exam_venue,1', 'integer', 'between:1,' . MAX_ROOM_CAPACITY])
                ->example(30),

            Column::make('is_exam_venue')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
