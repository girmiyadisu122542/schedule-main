<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Physical\Building;
use App\Models\Physical\Campus;

/**
 * Buildings — the middle tier of the physical hierarchy (Final Schema.md §2).
 *
 * `campus_code` must already exist: campuses are not importable, so the campus
 * list is seeded or entered by hand before a building sheet is loaded.
 */
class BuildingColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'building';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Building::class;
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
        return ['campus'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('code')
                ->required()
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('NB'),

            Column::make('name')
                ->required()
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_NAME_LENGTH])
                ->example('New Block'),

            Column::make('campus_code', 'campus_id')
                ->required()
                ->resolvesTo(Campus::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('MAIN')
                ->exportUsing(fn ($building) => $building->campus?->code),

            // Informational only — no floor-level entity is modelled.
            Column::make('floors')
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:' . MIN_BUILDING_FLOORS . ',' . MAX_BUILDING_FLOORS])
                ->example(4),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
