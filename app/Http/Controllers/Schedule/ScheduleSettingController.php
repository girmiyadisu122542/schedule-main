<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ScheduleSettingRequest;
use App\Models\Schedule\ScheduleSetting;
use Constants\AppConstant;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Translation\Message;

/**
 * The generation grid, per study mode — what used to be hardcoded in
 * `App\Constants\ScheduleConstant`.
 *
 * There is no `destroy`: a grid belongs to a seeded study mode, so it is
 * deactivated rather than deleted. Deleting the only regular grid would send
 * every programme back to the constants with nothing on screen to explain why.
 */
class ScheduleSettingController extends Controller {

    /** Relations every read needs. */
    private const EAGER = ['studyMode', 'user'];

    /**
     * List the configured grids.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeScheduleSetting() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $isActive = $request->input('is_active');

        $settings = ScheduleSetting::query()
            ->with(self::EAGER)
            ->when($request->input('study_mode_lookup_value_id'), fn ($query) => $query->where(
                'study_mode_lookup_value_id',
                (int) $request->input('study_mode_lookup_value_id'),
            ))
            ->when($request->input('study_mode_code'), fn ($query) => $query->whereHas(
                'studyMode',
                fn ($mode) => $mode->where('code', $request->input('study_mode_code')),
            ))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            // The catalogue order the modes were seeded in reads best.
            ->orderBy('study_mode_lookup_value_id')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $settings->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => ScheduleSetting::extractPagination($settings),
        ]);
    }

    /**
     * Show one grid by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeScheduleSetting()) {
            return Response::_403();
        }

        $setting = ScheduleSetting::query()
            ->with(self::EAGER)
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$setting) {
            return Response::_404(Message::get('schedule_setting_not_found'));
        }

        return Response::_200([
            'data' => $setting->resource(),
        ]);
    }

    /**
     * Configure the grid for a study mode that has none yet.
     *
     * @param \App\Http\Requests\Schedule\ScheduleSettingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ScheduleSettingRequest $request): JsonResponse {
        try {
            $setting = $this->persist(new ScheduleSetting(), $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_schedule_setting'));
        }

        return Response::_201([
            'data' => $setting->fresh(self::EAGER)->resource(),
            'message' => Message::get('schedule_setting_created_successfully'),
        ]);
    }

    /**
     * Adjust a grid — the teaching days, the day window, the period length or
     * lunch. It takes effect on the next generation run.
     *
     * @param \App\Http\Requests\Schedule\ScheduleSettingRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ScheduleSettingRequest $request, $id): JsonResponse {
        $setting = ScheduleSetting::find($id);
        if (!$setting) {
            return Response::_404(Message::get('schedule_setting_not_found'));
        }

        try {
            $setting = $this->persist($setting, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_schedule_setting'));
        }

        return Response::_200([
            'data' => $setting->fresh(self::EAGER)->resource(),
            'message' => Message::get('schedule_setting_updated_successfully'),
        ]);
    }

    /**
     * Write a grid in one transaction.
     *
     * `teaching_days` is sorted and deduplicated on the way in so the stored
     * array reads in week order however the form sent it.
     *
     * @param \App\Models\Schedule\ScheduleSetting $setting
     * @param array $data validated request payload
     *
     * @return \App\Models\Schedule\ScheduleSetting
     */
    private function persist(ScheduleSetting $setting, array $data): ScheduleSetting {
        $days = array_values(array_unique(array_map('intval', $data['teaching_days'])));
        sort($days);

        $examDays = array_values(array_unique(array_map('intval', $data['exam_days'])));
        sort($examDays);

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $setting->fill([
                'study_mode_lookup_value_id' => (int) $data['study_mode_lookup_value_id'],
                'teaching_days' => $days,
                'day_start' => $data['day_start'],
                'day_end' => $data['day_end'],
                'period_minutes' => (int) $data['period_minutes'],
                'break_minutes' => (int) ($data['break_minutes'] ?? 0),
                'lunch_start' => $data['lunch_start'] ?? null,
                'lunch_end' => $data['lunch_end'] ?? null,
                'exam_days' => $examDays,
                'exam_day_start' => $data['exam_day_start'],
                'exam_day_end' => $data['exam_day_end'],
                'exam_duration_minutes' => (int) $data['exam_duration_minutes'],
                'exam_gap_minutes' => (int) ($data['exam_gap_minutes'] ?? 0),
                'exam_period_days' => (int) $data['exam_period_days'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $setting->user_id ??= Auth::id();
            $setting->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $setting->refresh();
    }
}
