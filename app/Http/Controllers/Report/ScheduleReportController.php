<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\ScopesSchedulesToDepartment;
use App\Http\Controllers\Controller;
use App\Services\Export\ReportExportService;
use App\Services\Report\ScheduleReportService;
use App\Services\Report\TermSetupService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Reporting over a semester's timetable.
 *
 * Read-only throughout, so there is no store/update/destroy and no state. Each
 * action needs a semester: every figure here is meaningless without one, and
 * defaulting to "all time" would silently average two terms together.
 *
 * A department user sees their own departments. That is the same
 * `DepartmentScopeService` rule the scheduling screens use, applied here to the
 * one report where it changes the numbers — workload is per person, and people
 * belong to departments.
 */
class ScheduleReportController extends Controller {

    use ScopesSchedulesToDepartment;

    /**
     * Room utilisation for a semester.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function roomUtilisation(Request $request) {
        if (!$this->userCanSeeReport()) {
            return Response::_403();
        }

        $semesterId = (int) $request->input('semester_id');
        if (!$semesterId) {
            return Response::_422(Message::get('semester_is_required'));
        }

        $report = app(ScheduleReportService::class)->roomUtilisation($semesterId, [
                'building_id' => $request->input('building_id'),
                'campus_id' => $request->input('campus_id'),
            ]);

        // Same computed result the JSON endpoint returns, filters and
        // all — so an export is exactly what the reader was looking at.
        if ($request->filled('export')) {
            return app(ReportExportService::class)->download(
                $report,
                'room-utilisation',
                (string) $request->input('export'),
            );
        }

        return Response::_200(['data' => $report]);
    }

    /**
     * Instructor workload against each person's declared ceiling.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function instructorWorkload(Request $request) {
        if (!$this->userCanSeeReport()) {
            return Response::_403();
        }

        $semesterId = (int) $request->input('semester_id');
        if (!$semesterId) {
            return Response::_422(Message::get('semester_is_required'));
        }

        $departmentId = $request->input('department_id');

        // A department user gets their own department whether they asked for it
        // or not — and may not ask for somebody else's.
        $scope = app(\App\Services\User\DepartmentScopeService::class)->departmentIds();
        if ($scope !== null) {
            if ($departmentId && !in_array((int) $departmentId, $scope, true)) {
                return Response::_403();
            }

            $departmentId ??= $scope[0] ?? -1;
        }

        $report = app(ScheduleReportService::class)->instructorWorkload($semesterId, [
                'department_id' => $departmentId,
            ]);

        // Same computed result the JSON endpoint returns, filters and
        // all — so an export is exactly what the reader was looking at.
        if ($request->filled('export')) {
            return app(ReportExportService::class)->download(
                $report,
                'instructor-workload',
                (string) $request->input('export'),
            );
        }

        return Response::_200(['data' => $report]);
    }

    /**
     * Everything still wrong with a semester — the registrar's morning screen.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exceptions(Request $request) {
        if (!$this->userCanSeeReport()) {
            return Response::_403();
        }

        $semesterId = (int) $request->input('semester_id');
        if (!$semesterId) {
            return Response::_422(Message::get('semester_is_required'));
        }

        $report = app(ScheduleReportService::class)->exceptions($semesterId);

        // Same computed result the JSON endpoint returns, filters and
        // all — so an export is exactly what the reader was looking at.
        if ($request->filled('export')) {
            return app(ReportExportService::class)->download(
                $report,
                'exceptions',
                (string) $request->input('export'),
            );
        }

        return Response::_200(['data' => $report]);
    }

    /**
     * Two semesters side by side.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function compare(Request $request) {
        if (!$this->userCanSeeReport()) {
            return Response::_403();
        }

        $semesterId = (int) $request->input('semester_id');
        $compareId = (int) $request->input('compare_semester_id');

        if (!$semesterId || !$compareId) {
            return Response::_422(Message::get('semester_is_required'));
        }

        $report = app(ScheduleReportService::class)->compare($semesterId, $compareId);

        // Same computed result the JSON endpoint returns, filters and
        // all — so an export is exactly what the reader was looking at.
        if ($request->filled('export')) {
            return app(ReportExportService::class)->download(
                $report,
                'compare',
                (string) $request->input('export'),
            );
        }

        return Response::_200(['data' => $report]);
    }

    /**
     * Is this term ready to schedule? (C37)
     *
     * Read-only, and gated on seeing a report rather than on any one master-data
     * permission: it is a status page over things the user may already see, and
     * requiring fourteen separate permissions to read one checklist would put
     * it out of reach of exactly the new coordinator it exists for.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function termSetup(Request $request) {
        if (!$this->userCanSeeReport()) {
            return Response::_403();
        }

        $semesterId = (int) $request->input('semester_id');
        if (!$semesterId) {
            return Response::_422(Message::get('semester_is_required'));
        }

        $report = app(TermSetupService::class)->checklist($semesterId);

        // This report's rows live under `steps`, not `rows` — it is a checklist,
        // not a table of figures — so it is handed over in the shape the
        // exporter reads. The remaining keys are the summary.
        if ($request->filled('export')) {
            return app(ReportExportService::class)->download(
                [
                    'rows' => $report['steps'] ?? [],
                    'totals' => [
                        'ready' => $report['ready'] ?? false,
                        'complete' => $report['complete'] ?? 0,
                        'total' => $report['total'] ?? 0,
                    ],
                ],
                'term-setup',
                (string) $request->input('export'),
            );
        }

        return Response::_200(['data' => $report]);
    }
}
