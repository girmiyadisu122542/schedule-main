<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Schedule\ScheduleGenerationRun;
use App\Services\Schedule\ScheduleSnapshotService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Translation\Message;

/**
 * Run history — read only. A run is telemetry written by the generator; nothing
 * outside it may create or edit one.
 */
class ScheduleGenerationRunController extends Controller {

    /** Relations every read needs to render a run row. */
    private const EAGER = ['semester', 'type', 'status', 'runBy'];

    /**
     * List generation runs, newest first.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeScheduleGenerationRun() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $runs = ScheduleGenerationRun::query()
            ->with(self::EAGER)
            ->when($request->input('semester_id'), fn ($query) => $query->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->input('type_code'), fn ($query) => $query->whereHas('type', fn ($query) => $query->where('code', $request->input('type_code'))))
            ->when($request->input('status_code'), fn ($query) => $query->whereHas('status', fn ($query) => $query->where('code', $request->input('status_code'))))
            ->latest('started_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $runs->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => ScheduleGenerationRun::extractPagination($runs),
        ]);
    }

    /**
     * Show a run by numeric id OR uuid — see CLAUDE Sec. 10.18. This is the
     * endpoint the progress UI polls while a run is `running`.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeScheduleGenerationRun()) {
            return Response::_403();
        }

        $run = ScheduleGenerationRun::query()
            ->with(self::EAGER)
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$run) {
            return Response::_404(Message::get('schedule_generation_run_not_found'));
        }

        return Response::_200([
            'data' => $run->resource(),
        ]);
    }

    /**
     * Put back the timetable this run produced (C41).
     *
     * The snapshot is replayed through ordinary inserts, so every EXCLUDE still
     * applies. Rows that no longer fit — because something else has since taken
     * the slot — are reported rather than forced, and the response says how
     * many of each so the user knows whether the undo was complete.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id): JsonResponse {
        $run = ScheduleGenerationRun::with('type')->find($id);
        if (!$run) {
            return Response::_404(Message::get('generation_run_not_found'));
        }

        // Restoring WRITES a timetable, so it takes the same permission as
        // producing one — and specifically the one for this run's own kind,
        // since exam scheduling and class scheduling are granted separately.
        $allowed = $run->type?->code === GENERATION_TYPE_EXAM
            ? $this->userCanRunExamScheduleGeneration()
            : $this->userCanRunClassScheduleGeneration();

        if (!$allowed) {
            return Response::_403();
        }

        try {
            $result = app(ScheduleSnapshotService::class)->restore($run);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_restore_generation_run'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        return Response::_200([
            'data' => $result,
            'message' => Message::get('generation_run_restored_successfully', [
                'restored' => $result['restored'],
                'rejected' => $result['rejected'],
            ]),
        ]);
    }
}
