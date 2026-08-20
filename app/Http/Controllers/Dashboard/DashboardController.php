<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Academic\Semester;
use App\Models\Offering\CourseOffering;
use App\Models\Schedule\ClassSchedule;
use App\Models\Schedule\ExamSchedule;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;

/**
 * Read-only aggregates for the landing screen.
 *
 * No service layer and no migration: every figure is a count over an existing
 * table, and there is no business rule to put anywhere else. The counts are
 * scoped to the current semester, because a dashboard that mixes terms answers
 * nothing.
 */
class DashboardController extends Controller {

    /**
     * The four figures the dashboard shows, in one response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(): JsonResponse {
        // No permission gate. The landing screen is the first thing every
        // signed-in user sees, so gating it on `see:dashboard` meant any role
        // that had not been granted that permission — including every role
        // created after the seeder ran — logged in to a 403 instead of a home
        // page. Authentication is the only thing this needs; the route already
        // sits behind the API guard.
        //
        // Nothing here leaks: the figures are current-semester totals, the same
        // summary for everyone, and each detail screen behind them still
        // enforces its own permission.
        $semester = Semester::with('status')->where('is_current', true)->first();

        return Response::_200([
            'current_semester' => $semester ? [
                'id' => $semester->id,
                'uuid' => $semester->uuid,
                'name' => $semester->name__localized,
                'status_code' => $semester->status?->code,
                'status_label' => $semester->status?->name__localized,
                'start_date' => $semester->start_date?->format(DATE_FORMAT),
                'end_date' => $semester->end_date?->format(DATE_FORMAT),
            ] : null,
            'registrar_approved_offerings_count' => $this->offeringsReadyToSchedule($semester?->id),
            'published_class_schedules_count' => $this->publishedClassMeetings($semester?->id),
            'upcoming_published_exams_count' => $this->upcomingPublishedExams($semester?->id),
        ]);
    }

    /**
     * Offerings the registrar has approved — the pool the generators draw from.
     *
     * @param int|null $semesterId
     * @return int
     */
    private function offeringsReadyToSchedule(?int $semesterId): int {
        if (!$semesterId) {
            return 0;
        }

        return CourseOffering::query()
            ->where('semester_id', $semesterId)
            ->whereHas('status', fn ($query) => $query->where('code', COURSE_OFFERING_STATUS_REGISTRAR_APPROVED))
            ->count();
    }

    /**
     * Class meetings students can actually read.
     *
     * @param int|null $semesterId
     * @return int
     */
    private function publishedClassMeetings(?int $semesterId): int {
        if (!$semesterId) {
            return 0;
        }

        return ClassSchedule::query()
            ->where('semester_id', $semesterId)
            ->whereHas('status', fn ($query) => $query->where('code', CLASS_SCHEDULE_STATUS_PUBLISHED))
            ->count();
    }

    /**
     * Published sittings still to come — a past exam is not something to act on.
     *
     * @param int|null $semesterId
     * @return int
     */
    private function upcomingPublishedExams(?int $semesterId): int {
        if (!$semesterId) {
            return 0;
        }

        return ExamSchedule::query()
            ->where('semester_id', $semesterId)
            ->whereDate('exam_date', '>=', now()->toDateString())
            ->whereHas('status', fn ($query) => $query->where('code', EXAM_SCHEDULE_STATUS_PUBLISHED))
            ->count();
    }
}
