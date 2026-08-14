<?php

namespace App\Support\Import\ColumnMap;

use App\Constants\ImportConstant;
use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Offering\CourseOffering;
use App\Models\Offering\CourseOfferingSection;
use App\Models\People\Instructor;
use App\Services\Lookup\LookupService;
use App\Services\Offering\CourseOfferingService;
use App\Services\User\DepartmentScopeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Course offerings — the plan a department files for one semester.
 *
 * Two rules here are not conveniences, they are the reason this map can exist
 * at all:
 *
 *  1. **Every imported offering lands as `draft`.** `status` is export-only. A
 *     writable status column would be a spreadsheet route straight to
 *     `registrar_approved`, and from there into the timetable, with no approval
 *     and no trail — the same hole the `change-status` endpoint was deleted for.
 *  2. **Every row is checked against the importer's own department scope.** A
 *     sheet must not be able to file offerings for a department the uploader
 *     could not file one for through the UI.
 *
 * Two columns are composite because their targets have no stable code:
 * `semesters` are identified by `(academic_year, term)` and `sections` by
 * `(program, year_level, label)`. Both use the same `A|B|C` syntax as the
 * cross-listing column, so a registrar learns one shape, not three.
 */
class OfferingColumnMap extends AbstractColumnMap {

    /**
     * @return string
     */
    public function entityKey(): string {
        return 'course_offering';
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string {
        return CourseOffering::class;
    }

    /**
     * The composite unique the database already enforces —
     * `(semester_id, course_id, section_id)`.
     *
     * @return array<int, string>
     */
    public function naturalKey(): array {
        return ['semester', 'course_code', 'section'];
    }

    /**
     * `section_id` is nullable, and a partial unique covers the null case: an
     * offering made to a whole programme rather than one section.
     *
     * @return array<int, string>
     */
    public function nullableKeyParts(): array {
        return ['section'];
    }

    /**
     * @return array<int, string>
     */
    public function exportWith(): array {
        return [
            'semester.academicYear',
            'course',
            'department',
            'program',
            'section.program',
            'instructor',
            'status',
            'additionalSections.section.program',
        ];
    }

    /**
     * `course_offerings` has no `user_id`; the creator column is
     * `created_by_id`. Stamping `user_id` would hit a column that does not
     * exist.
     *
     * @return bool
     */
    public function stampsCreator(): bool {
        return false;
    }

    /**
     * @return string|null
     */
    public function creatorAttribute(): ?string {
        return 'created_by_id';
    }

    /**
     * Pin every created row to `draft`, whatever the sheet says.
     *
     * @return array<string, mixed>
     */
    public function defaultAttributes(): array {
        return [
            'status_lookup_value_id' => LookupService::getValueByCode(COURSE_OFFERING_STATUS, COURSE_OFFERING_STATUS_DRAFT, needId: true),
            'status_changed_at' => now(),
        ];
    }

    /**
     * @return array<int, \App\Support\Import\ColumnMap\Column>
     */
    public function columns(): array {
        return [
            Column::make('semester', 'semester_id')
                ->required()
                ->resolvesUsing(fn (array $values) => $this->resolveSemesters($values))
                ->rules(['string'])
                ->example('2025/26 S1')
                ->exportUsing(fn ($offering) => $this->semesterLabel($offering->semester)),

            Column::make('course_code', 'course_id')
                ->required()
                ->resolvesTo(Course::class, 'code')
                ->rules(['string', 'max:' . MAX_ROOM_CODE_LENGTH])
                ->example('CS101')
                ->exportUsing(fn ($offering) => $offering->course?->code),

            Column::make('department_code', 'department_id')
                ->required()
                ->resolvesTo(Department::class, 'code')
                ->rules(['string', 'max:' . MAX_CAMPUS_CODE_LENGTH])
                ->example('CS')
                ->exportUsing(fn ($offering) => $offering->department?->code),

            Column::make('program_code', 'program_id')
                ->resolvesTo(Program::class, 'code')
                ->rules(['string', 'max:' . MAX_ROOM_CODE_LENGTH])
                ->example('BSC-CS')
                ->exportUsing(fn ($offering) => $offering->program?->code),

            Column::make('section', 'section_id')
                ->resolvesUsing(fn (array $values) => $this->resolveSections($values))
                ->rules(['string'])
                ->example('BSC-CS|1|A')
                ->exportUsing(fn ($offering) => $this->sectionLabel($offering->section)),

            Column::make('instructor_employee_no', 'instructor_id')
                ->resolvesTo(Instructor::class, 'employee_no')
                ->rules(['string', 'max:' . MAX_CODE_LENGTH])
                ->example('EMP-1001')
                ->exportUsing(fn ($offering) => $offering->instructor?->employee_no),

            Column::make('expected_students')
                ->type(Column::TYPE_INTEGER)
                ->rules(['integer', 'between:0,' . MAX_SECTION_EXPECTED_STUDENTS])
                ->example(45),

            // The other cohorts attending a cross-listed offering. The OWNING
            // section stays on `section`, so "whose offering is this" keeps one
            // answer. Type `-` to clear the list; a blank cell leaves it alone,
            // because a reader cannot tell "blank" from "column not filled in".
            Column::make('additional_sections')
                ->rules(['string'])
                ->example('BSC-SE|2|A; BSC-CS|2|B')
                ->exportUsing(fn ($offering) => $this->additionalSectionLabels($offering)),

            Column::make('remark')
                ->rules(['string', 'max:' . MAX_DESCRIPTION_LENGTH])
                ->example('Shared lab slot'),

            // Readable, never writable — see the class docblock.
            Column::make('status')
                ->exportOnly()
                ->example(null)
                ->exportUsing(fn ($offering) => $offering->status?->name__localized),
        ];
    }

    /**
     * Rules the resolved row must satisfy, with the record it would update.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $attributes
     * @param \Illuminate\Database\Eloquent\Model|null $record
     *
     * @return array<int, array{column: string|null, key: string}>
     */
    public function validateResolvedRow(array $values, array $attributes, $record): array {
        $errors = [];
        $scope = app(DepartmentScopeService::class);

        if (!$scope->allows($attributes['department_id'] ?? null)) {
            $errors[] = ['column' => 'department_code', 'key' => 'import_department_outside_your_scope'];
        }

        // On upsert the department the row ALREADY sits in matters too:
        // otherwise a caller could lift another department's offering into
        // their own simply by naming their own department in the sheet.
        if ($record && !$scope->allows($record->department_id ? (int) $record->department_id : null)) {
            $errors[] = ['column' => 'department_code', 'key' => 'import_offering_outside_your_scope'];
        }

        // The import-side twin of `offering_is_locked_for_editing`: an offering
        // the tiers are already voting on is not a spreadsheet's to rewrite.
        if ($record && !in_array($record->status?->code, CourseOfferingService::EDITABLE_STATUS_CODES, true)) {
            $errors[] = ['column' => 'course_code', 'key' => 'import_offering_is_locked'];
        }

        foreach ($this->unresolvedAdditionalSections($values, $attributes) as $token) {
            $errors[] = ['column' => 'additional_sections', 'key' => 'import_additional_section_not_found'];
        }

        return $errors;
    }

    /**
     * Replace the cross-listing after the offering itself is written.
     *
     * @param \Illuminate\Database\Eloquent\Model $record
     * @param array<string, mixed> $values
     *
     * @return void
     */
    public function afterWrite($record, array $values): void {
        $cell = trim((string) ($values['additional_sections'] ?? ''));

        // Blank leaves the cross-listing alone; the sentinel clears it.
        if ($cell === '') {
            return;
        }

        $sectionIds = $cell === ImportConstant::CLEAR_SENTINEL
            ? []
            : $this->sectionIdsFor($this->splitTokens($cell));

        // The owning section is never repeated as an additional one.
        $sectionIds = array_values(array_filter($sectionIds, fn (int $id) => $id !== (int) $record->section_id));

        CourseOfferingSection::query()
            ->where('course_offering_id', $record->id)
            ->whereNotIn('section_id', $sectionIds ?: [0])
            ->delete();

        foreach ($sectionIds as $sectionId) {
            CourseOfferingSection::firstOrCreate(
                ['course_offering_id' => $record->id, 'section_id' => $sectionId],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => Auth::id(),
                    'expected_students' => Section::query()->whereKey($sectionId)->value('expected_students'),
                ],
            );
        }
    }

    /**
     * Batched `academic_year S<term>` → semester id.
     *
     * @param array<string, string> $values lowered => original
     * @return array<string, int>
     */
    private function resolveSemesters(array $values): array {
        $resolved = [];

        $semesters = Semester::query()->with('academicYear')->get();

        foreach ($semesters as $semester) {
            $resolved[mb_strtolower($this->semesterLabel($semester))] = (int) $semester->id;
        }

        return array_intersect_key($resolved, $values);
    }

    /**
     * Batched `program|year_level|label` → section id.
     *
     * @param array<string, string> $values lowered => original
     * @return array<string, int>
     */
    private function resolveSections(array $values): array {
        $resolved = [];

        $sections = Section::query()->with('program')->get();

        foreach ($sections as $section) {
            $resolved[mb_strtolower($this->sectionLabel($section))] = (int) $section->id;
        }

        return array_intersect_key($resolved, $values);
    }

    /**
     * `2025/26 S1` — the academic year's code plus the term number.
     *
     * @param \App\Models\Academic\Semester|null $semester
     * @return string
     */
    private function semesterLabel($semester): string {
        return $semester ? trim(($semester->academicYear?->code ?? '') . ' S' . $semester->term) : '';
    }

    /**
     * `BSC-CS|1|A` — programme code, year level, label.
     *
     * @param \App\Models\Academic\Section|null $section
     * @return string
     */
    private function sectionLabel($section): string {
        if (!$section) {
            return '';
        }

        return implode(ImportConstant::COMPOSITE_KEY_SEPARATOR, [
            $section->program?->code,
            $section->year_level,
            $section->label,
        ]);
    }

    /**
     * The cross-listed cohorts, as one delimited cell.
     *
     * @param \App\Models\Offering\CourseOffering $offering
     * @return string|null
     */
    private function additionalSectionLabels($offering): ?string {
        $labels = $offering->additionalSections
            ->map(fn ($extra) => $this->sectionLabel($extra->section))
            ->filter()
            ->all();

        return $labels ? implode(ImportConstant::MULTI_VALUE_SEPARATOR . ' ', $labels) : null;
    }

    /**
     * Split a delimited cell into trimmed tokens.
     *
     * @param string $cell
     * @return array<int, string>
     */
    private function splitTokens(string $cell): array {
        $tokens = explode(ImportConstant::MULTI_VALUE_SEPARATOR, $cell);

        return array_values(array_filter(array_map('trim', $tokens)));
    }

    /**
     * Resolve section tokens to ids, dropping any that do not match.
     *
     * @param array<int, string> $tokens
     * @return array<int, int>
     */
    private function sectionIdsFor(array $tokens): array {
        if (!$tokens) {
            return [];
        }

        $wanted = [];
        foreach ($tokens as $token) {
            $wanted[mb_strtolower($token)] = $token;
        }

        return array_values($this->resolveSections($wanted));
    }

    /**
     * The tokens in the cross-listing cell that name no section.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $attributes
     *
     * @return array<int, string>
     */
    private function unresolvedAdditionalSections(array $values, array $attributes): array {
        $cell = trim((string) ($values['additional_sections'] ?? ''));

        if ($cell === '' || $cell === ImportConstant::CLEAR_SENTINEL) {
            return [];
        }

        $tokens = $this->splitTokens($cell);
        $resolvedCount = count($this->sectionIdsFor($tokens));

        return $resolvedCount === count($tokens) ? [] : $tokens;
    }
}
