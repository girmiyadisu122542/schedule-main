<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\GenerateClassScheduleRequest;
use App\Services\Schedule\ClassScheduleGeneratorService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Translation\Message;

/**
 * Triggering automatic class scheduling. The run row it returns is what the
 * progress UI polls (`GET /schedule/generation-runs/{key}`).
 */
class ClassScheduleGeneratorController extends Controller {

    /**
     * Generate the class timetable for one semester.
     *
     * @param \App\Http\Requests\Schedule\GenerateClassScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(GenerateClassScheduleRequest $request): JsonResponse {
        try {
            $result = app(ClassScheduleGeneratorService::class)->generate((int) $request->validated('semester_id'), (bool) $request->boolean('dry_run'));
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_generate_class_schedules'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_201([
            'data' => $result->fresh(['semester', 'type', 'status', 'runBy'])->resource(),
            'message' => Message::get($request->boolean('dry_run')
                ? 'class_schedule_dry_run_completed'
                : 'class_schedule_generation_completed', [
                'scheduled' => $result->scheduled_count,
                'unplaced' => $result->unplaced_count,
            ]),
        ]);
    }
}
