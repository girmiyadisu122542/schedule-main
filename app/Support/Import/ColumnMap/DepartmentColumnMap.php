<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\User;

/**
 * Departments — the ownership root for programs, courses, instructors and
 * offerings (Final Schema.md §4).
 *
 * The routing pointer here is `head_user_email` → `head_user_id`. A department
 * has a HEAD; the DEAN lives on `colleges`, one level up — see
 * {@see \App\Support\Import\ColumnMap\CollegeColumnMap}.
 */
class DepartmentColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'department';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Department::class;
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
        return ['college', 'head'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('code')
                ->required()
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('CS'),

            Column::make('name')
                ->required()
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_NAME_LENGTH])
                ->example('Computer Science'),

            Column::make('college_code', 'college_id')
                ->required()
                ->resolvesTo(College::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('COET')
                ->exportUsing(fn ($department) => $department->college?->code),

            Column::make('head_user_email', 'head_user_id')
                ->resolvesTo(User::class, 'email')
                ->rules(['string', 'max:' . MAX_INSTRUCTOR_EMAIL_LENGTH])
                ->example('head.cs@schedule.com')
                ->exportUsing(fn ($department) => $department->head?->email),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
