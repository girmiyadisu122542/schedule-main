<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\GenerateExamScheduleRequest;
use App\Services\Lookup\LookupService;
use App\Services\Schedule\ExamScheduleGeneratorService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Translation\Message;

/**
 * Triggering automatic exam scheduling. The run row it returns is the same
 * `schedule_generation_runs` shape the class generator writes, so the progress
 * UI reads both through one endpoint.
 */
class ExamScheduleGeneratorController extends Controller {

    /**
     * Generate the exam timetable for one semester.
     *
     * @param \App\Http\Requests\Schedule\GenerateExamScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(GenerateExamScheduleRequest $request): JsonResponse {
        $examTypeId = $request->validated('exam_type_lookup_value_id');

        // The service speaks in codes, not ids — a run defaults to finals.
        $examTypeCode = $examTypeId
            ? (LookupService::getValueById((int) $examTypeId)?->code ?? EXAM_TYPE_FINAL)
            : EXAM_TYPE_FINAL;

        try {
            $result = app(ExamScheduleGeneratorService::class)->generate(
                (int) $request->validated('semester_id'),
                $examTypeCode,
            );
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_generate_exam_schedules'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(['semester', 'type', 'status', 'runBy'])->resource(),
            'message' => Message::get('exam_schedule_generation_completed', [
                'scheduled' => $result->scheduled_count,
                'unplaced' => $result->unplaced_count,
            ]),
        ]);
    }
}
