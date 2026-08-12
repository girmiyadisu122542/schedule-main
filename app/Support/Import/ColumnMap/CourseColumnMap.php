<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Academic\Department;
use App\Models\Catalogue\Course;

/**
 * Courses — the reusable catalogue (Final Schema.md §10).
 *
 * Two column names differ from what a reader might guess, and both follow the
 * schema rather than convenience: the translatable name column is `title`, not
 * `name`, and the weekly-load columns carry the `_per_week` suffix. The sheet
 * uses the real column names so an export round-trips without a rename step.
 */
class CourseColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'course';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Course::class;
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
        return ['department', 'courseType'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            // Globally unique: it prints bare on timetables and exam papers,
            // where a reader has no department context to disambiguate.
            Column::make('code')
                ->required()
                ->rules(['string', 'max:' . MAX_ROOM_CODE_LENGTH])
                ->example('CS101'),

            Column::make('title')
                ->required()
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_LONG_NAME_LENGTH])
                ->example('Introduction to Programming'),

            Column::make('department_code', 'department_id')
                ->required()
                ->resolvesTo(Department::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('CS')
                ->exportUsing(fn ($course) => $course->department?->code),

            // NOT NULL on the table — a sheet without it can never import.
            Column::make('course_type_code', 'course_type_lookup_value_id')
                ->required()
                ->resolvesToLookup(COURSE_TYPE)
                ->rules(['string'])
                ->example(COURSE_TYPE_LECTURE_LAB)
                ->exportUsing(fn ($course) => $course->courseType?->code),

            Column::make('credit_hours')
                ->required()
                ->type(Column::TYPE_DECIMAL)
                ->rules(['numeric', 'between:' . MIN_COURSE_HOURS . ',' . MAX_COURSE_HOURS])
                ->example(3),

            Column::make('contact_hours')
                ->type(Column::TYPE_DECIMAL)
                ->rules(['numeric', 'between:' . MIN_COURSE_HOURS . ',' . MAX_COURSE_HOURS])
                ->example(5),

            // Weekly load — what the class generator fans out into meetings.
            Column::make('lecture_hours_per_week')
                ->type(Column::TYPE_DECIMAL)
                ->rules(['numeric', 'between:0,' . MAX_COURSE_HOURS])
                ->example(3),

            Column::make('lab_hours_per_week')
                ->type(Column::TYPE_DECIMAL)
                ->rules(['numeric', 'between:0,' . MAX_COURSE_HOURS])
                ->example(2),

            Column::make('tutorial_hours_per_week')
                ->type(Column::TYPE_DECIMAL)
                ->rules(['numeric', 'between:0,' . MAX_COURSE_HOURS])
                ->example(0),

            Column::make('sessions_per_week')
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:1,' . MAX_SESSIONS_PER_WEEK])
                ->example(2),

            Column::make('description')
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_DESCRIPTION_LENGTH])
                ->example('Foundations of programming using a modern language.'),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
