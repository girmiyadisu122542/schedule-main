<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Academic\Department;
use App\Models\Academic\Program;

/**
 * Programs — the degree a section's cohort is enrolled on (Final Schema.md §5).
 */
class ProgramColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'program';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Program::class;
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
        return ['department', 'degreeLevel'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('code')
                ->required()
                ->rules(['string', 'max:' . MAX_ROOM_CODE_LENGTH])
                ->example('BSC-CS'),

            Column::make('name')
                ->required()
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_NAME_LENGTH])
                ->example('BSc in Computer Science'),

            Column::make('department_code', 'department_id')
                ->required()
                ->resolvesTo(Department::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('CS')
                ->exportUsing(fn ($program) => $program->department?->code),

            Column::make('degree_level_code', 'degree_level_lookup_value_id')
                ->required()
                ->resolvesToLookup(DEGREE_LEVEL)
                ->rules(['string'])
                ->example(DEGREE_LEVEL_BACHELOR)
                ->exportUsing(fn ($program) => $program->degreeLevel?->code),

            Column::make('duration_years')
                ->required()
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:' . MIN_PROGRAM_DURATION_YEARS . ',' . MAX_PROGRAM_DURATION_YEARS])
                ->example(4),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
