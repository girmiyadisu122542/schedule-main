<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Program;
use App\Models\Academic\Section;

/**
 * Sections — the student cohort, and the row every student-group conflict rule
 * is checked against (Final Schema.md §8).
 *
 * Two things make this map unlike the others:
 *
 *  1. There is no `code` and no `name`. A section is identified by the composite
 *     unique `(program_id, academic_year_id, year_level, label)`, so that tuple
 *     IS the natural key — `upsert` matches on all four columns and
 *     `create_only` rejects a duplicate of the same four.
 *  2. It is scoped to the ACADEMIC YEAR, not the semester. "CS Year 2 Section A"
 *     is the same cohort across both semesters of the year, so the sheet carries
 *     `academic_year_code` and there is no semester column.
 */
class SectionColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'section';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Section::class;
    }

    /**
     * @return array<int, string>
     */
    public function naturalKey(): array {
        return ['program_code', 'academic_year_code', 'year_level', 'label'];
    }

    /**
     * @return array<int, string>
     */
    public function exportWith(): array {
        return ['program', 'academicYear'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('program_code', 'program_id')
                ->required()
                ->resolvesTo(Program::class, 'code')
                ->rules(['string', 'max:' . MAX_ROOM_CODE_LENGTH])
                ->example('BSC-CS')
                ->exportUsing(fn ($section) => $section->program?->code),

            Column::make('academic_year_code', 'academic_year_id')
                ->required()
                ->resolvesTo(AcademicYear::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('2025/26')
                ->exportUsing(fn ($section) => $section->academicYear?->code),

            Column::make('year_level')
                ->required()
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:' . MIN_SECTION_YEAR_LEVEL . ',' . MAX_SECTION_YEAR_LEVEL])
                ->example(2),

            Column::make('label')
                ->required()
                ->rules(['string', 'max:' . MAX_SECTION_LABEL_LENGTH])
                ->example('A'),

            Column::make('expected_students')
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:0,' . MAX_SECTION_EXPECTED_STUDENTS])
                ->example(45),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
