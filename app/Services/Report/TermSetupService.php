<?php

namespace App\Services\Report;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Department;
use App\Models\Academic\Program;
use App\Models\Academic\Section;
use App\Models\Academic\Semester;
use App\Models\Catalogue\Course;
use App\Models\Offering\CourseOffering;
use App\Models\People\Instructor;
use App\Models\Physical\Building;
use App\Models\Physical\Campus;
use App\Models\Physical\Room;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ScheduleSetting;
use App\Services\Lookup\LookupService;

/**
 * Is this term ready to schedule? (C37)
 *
 * Standing up a term means visiting a dozen master-data screens in a dependency
 * order the UI never states — campus before building before room, department
 * before programme before section, everything before an offering. A new
 * coordinator cannot do it unaided, and the current failure mode is a
 * generation run that places nothing and does not say why.
 *
 * A CHECKLIST rather than a wizard, deliberately. A wizard would march an
 * experienced registrar through fourteen screens they do not need; a checklist
 * says what is missing, links to the screen that fixes it, and gets out of the
 * way. It is also honest about order: each step names what it depends on, so
 * the reason something is blocked is visible rather than inferred.
 */
class TermSetupService {

    /**
     * Every prerequisite for one semester, in dependency order.
     *
     * @param int $semesterId
     * @return array
     */
    public function checklist(int $semesterId): array {
        $semester = Semester::with('academicYear')->find($semesterId);
        if (!$semester) {
            return ['steps' => [], 'ready' => false, 'complete' => 0, 'total' => 0];
        }

        $approvedId = LookupService::getValueByCode(
            COURSE_OFFERING_STATUS,
            COURSE_OFFERING_STATUS_REGISTRAR_APPROVED,
            needId: true,
        );

        $offerings = CourseOffering::where('semester_id', $semesterId);
        $approvedOfferings = (clone $offerings)->when($approvedId, fn ($q) => $q->where('status_lookup_value_id', $approvedId));

        $steps = [
            $this->step('campuses', Campus::where('is_active', true)->count(), 'physical'),
            $this->step('buildings', Building::where('is_active', true)->count(), 'physical', 'campuses'),
            $this->step('rooms', Room::where('is_active', true)->count(), 'physical', 'buildings'),
            $this->step('exam_venues', Room::where('is_active', true)->where('is_exam_venue', true)->count(), 'physical', 'rooms', optional: true),
            $this->step('departments', Department::where('is_active', true)->count(), 'academic'),
            $this->step('programs', Program::where('is_active', true)->count(), 'academic', 'departments'),
            $this->step('academic_years', AcademicYear::count(), 'academic'),
            $this->step('sections', Section::where('is_active', true)->where('academic_year_id', $semester->academic_year_id)->count(), 'academic', 'programs'),
            $this->step('courses', Course::where('is_active', true)->count(), 'catalogue', 'departments'),
            $this->step('instructors', Instructor::where('is_active', true)->where('can_teach', true)->count(), 'people', 'departments'),
            $this->step('schedule_settings', ScheduleSetting::where('is_active', true)->count(), 'configuration'),
            $this->step('offerings', (clone $offerings)->count(), 'offering', 'sections'),
            $this->step('approved_offerings', $approvedOfferings->count(), 'offering', 'offerings'),
            $this->step('class_schedules', ClassSchedule::where('semester_id', $semesterId)->count(), 'scheduling', 'approved_offerings', optional: true),
        ];

        // Ready when every REQUIRED step is satisfied. The optional ones —
        // exam venues, a generated timetable — are progress, not blockers.
        $required = array_filter($steps, fn (array $step): bool => !$step['is_optional']);
        $ready = !in_array(false, array_column($required, 'is_satisfied'), true);

        return [
            'semester_id' => $semesterId,
            'semester' => $semester->name__localized,
            'steps' => $steps,
            'ready' => $ready,
            'complete' => count(array_filter($steps, fn (array $step): bool => $step['is_satisfied'])),
            'total' => count($steps),
        ];
    }

    /**
     * One checklist row.
     *
     * @param string $key
     * @param int $count
     * @param string $group
     * @param string|null $dependsOn the step that has to come first
     * @param bool $optional
     *
     * @return array
     */
    private function step(string $key, int $count, string $group, ?string $dependsOn = null, bool $optional = false): array {
        return [
            'key' => $key,
            'group' => $group,
            'count' => $count,
            'is_satisfied' => $count > 0,
            'is_optional' => $optional,
            'depends_on' => $dependsOn,
        ];
    }
}
