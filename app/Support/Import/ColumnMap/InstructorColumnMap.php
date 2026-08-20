<?php

namespace App\Support\Import\ColumnMap;

use App\Models\Academic\Department;
use App\Models\People\Instructor;
use App\Models\User;
use App\Services\People\InstructorAccountService;

/**
 * Instructors — one population serving both teaching and invigilation
 * (Final Schema.md §11).
 *
 * The natural key is `employee_no`; this table has no `code` column. The
 * translatable name column is `full_name`, a jsonb snapshot that keeps rosters
 * correct even for instructors with no linked `users` row.
 *
 * `user_email` links THE PERSON to their portal account — nullable, because the
 * registry precedes the login. It is not a creator reference; this table has no
 * creator column at all.
 */
class InstructorColumnMap extends AbstractColumnMap {
    /**
     * @return string
     */
    public function entityKey(): string {
        return 'instructor';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return Instructor::class;
    }

    /**
     * Give the imported instructor their portal account.
     *
     * The import is the bulk half of the same rule the form follows: an
     * instructor arrives with a login, so a registrar who uploads eighty
     * teachers does not then have to create eighty users by hand. Idempotent,
     * so re-importing a sheet does not make second accounts.
     *
     * Runs inside the importer's transaction, so a failure here takes the whole
     * import back rather than leaving accounts for rows that were rolled back.
     *
     * @param \App\Models\People\Instructor $record
     * @param array $values the row's resolved cell values
     *
     * @return void
     */
    public function afterWrite($record, array $values): void {
        app(InstructorAccountService::class)->provision($record);
    }

    /**
     * @return array<int, string>
     */
    public function naturalKey(): array {
        return ['employee_no'];
    }

    /**
     * @return array<int, string>
     */
    public function exportWith(): array {
        return ['department', 'academicRank', 'person'];
    }

    /**
     * This table has no creator column — `user_id` is the person's portal
     * account, and stamping the importing user into it would claim every
     * imported instructor IS that user.
     *
     * @return bool
     */
    public function stampsCreator(): bool {
        return false;
    }

    /**
     * Someone who can neither teach nor invigilate has no role in the system;
     * the two flags are what let one table serve both populations. Mirrors
     * `InstructorService::guardInputs()`.
     *
     * @param array<string, mixed> $values the coerced row
     * @return array<int, array{column: string|null, key: string}>
     */
    public function validateRow(array $values): array {
        $canTeach = $values['can_teach'] ?? true;
        $canInvigilate = $values['can_invigilate'] ?? true;

        if (!$canTeach && !$canInvigilate) {
            return [['column' => 'can_teach', 'key' => 'instructor_needs_a_capability']];
        }

        return [];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('employee_no')
                ->required()
                ->rules(['string', 'max:' . MAX_CODE_LENGTH])
                ->example('EMP-1001'),

            Column::make('full_name')
                ->required()
                ->type(Column::TYPE_TRANSLATABLE)
                ->rules(['string', 'max:' . MAX_LONG_NAME_LENGTH])
                ->example('Dr. Alemu Bekele'),

            Column::make('department_code', 'department_id')
                ->required()
                ->resolvesTo(Department::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('CS')
                ->exportUsing(fn ($instructor) => $instructor->department?->code),

            // REQUIRED: importing an instructor also creates their portal
            // account, and this is the login it is created with.
            Column::make('email')
                ->required()
                ->rules(['email', 'max:' . MAX_INSTRUCTOR_EMAIL_LENGTH])
                ->example('alemu.bekele@schedule.com'),

            Column::make('phone')
                ->rules(['string', 'max:' . MAX_PHONE_LENGTH])
                ->example('+251911000001'),

            Column::make('academic_rank_code', 'academic_rank_lookup_value_id')
                ->resolvesToLookup(ACADEMIC_RANK)
                ->rules(['string'])
                ->example(ACADEMIC_RANK_ASSISTANT_PROFESSOR)
                ->exportUsing(fn ($instructor) => $instructor->academicRank?->code),

            // Rarely needed now: the account is provisioned from `email`
            // above. Kept so a sheet can point an instructor at an EXISTING
            // account whose address differs from their contact address.
            Column::make('user_email', 'user_id')
                ->resolvesTo(User::class, 'email')
                ->rules(['string', 'max:' . MAX_INSTRUCTOR_EMAIL_LENGTH])
                ->example('teacher@schedule.com')
                ->exportUsing(fn ($instructor) => $instructor->person?->email),

            // A lab technician who only invigilates is can_teach = No; a visiting
            // lecturer exempt from duty is the reverse. One of the two must be Yes.
            Column::make('can_teach')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),

            Column::make('can_invigilate')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),

            Column::make('max_weekly_hours')
                ->type(Column::TYPE_DECIMAL)
                ->rules(['numeric', 'between:1,' . MAX_INSTRUCTOR_WEEKLY_HOURS])
                ->example(18),

            Column::make('is_active')
                ->type(Column::TYPE_BOOLEAN)
                ->rules(['boolean'])
                ->example('Yes'),
        ];
    }
}
