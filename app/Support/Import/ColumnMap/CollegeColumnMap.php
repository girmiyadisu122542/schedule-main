<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Academic\College;
use App\Models\User;

/**
 * Colleges — the top of the academic hierarchy (Final Schema.md §3).
 *
 * `dean_user_email` resolves the routing pointer for the college-approval step.
 * It is nullable because a college can sit vacant between deans, and it is NOT
 * the authorization source — whether that user may act as Dean stays an RBAC
 * question.
 */
class CollegeColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'college';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return College::class;
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
        return ['dean'];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            // Required here even though the API auto-generates a blank code:
            // `code` is this sheet's natural key, and without it neither
            // duplicate detection nor upsert can identify a row.
            Column::make('code')
                ->required()
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('COET'),

            Column::make('name')
                ->required()
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_NAME_LENGTH])
                ->example('College of Engineering and Technology'),

            Column::make('dean_user_email', 'dean_user_id')
                ->resolvesTo(User::class, 'email')
                ->rules(['string', 'max:' . MAX_INSTRUCTOR_EMAIL_LENGTH])
                ->example('dean.coet@schedule.com')
                ->exportUsing(fn ($college) => $college->dean?->email),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
