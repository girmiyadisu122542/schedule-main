<?php

namespace Database\Seeders\Schedule;

use App\Models\Schedule\ScheduleSetting;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ScheduleSettingSeeder extends Seeder {

    /**
     * Seed one generation grid per study mode.
     *
     * The regular grid reproduces exactly what
     * `ScheduleConstant::GENERATION_TIME_SLOTS` used to hardcode — 08:00 to
     * 17:15, 90-minute periods, 15-minute breaks, lunch 13:00–14:00 — so
     * switching the generator over changes nothing until a registrar edits it.
     *
     * The extension grid is the answer to "when do weekend students sit":
     * Saturday and Sunday, longer days, no lunch break carved out.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('ScheduleSettingSeeder cannot proceed: no user found.');
            return;
        }

        $grids = [
            STUDY_MODE_REGULAR => [
                'teaching_days' => [1, 2, 3, 4, 5],
                'day_start' => '08:00',
                'day_end' => '17:15',
                'period_minutes' => 90,
                'break_minutes' => 15,
                'lunch_start' => '13:00',
                'lunch_end' => '14:00',
                // Exams run longer than a period and need the hall turned
                // round between sittings, so they get their own window.
                'exam_days' => [1, 2, 3, 4, 5, 6],
                'exam_day_start' => '09:00',
                'exam_day_end' => '17:00',
                'exam_duration_minutes' => 180,
                // 120, not 60: it reproduces the old hardcoded 09:00–12:00 and
                // 14:00–17:00 exactly, so switching the generator over changes
                // nothing until a registrar edits it.
                'exam_gap_minutes' => 120,
                'exam_period_days' => 14,
            ],
            // Saturday and Sunday, start to finish — the weekend intake.
            STUDY_MODE_EXTENSION => [
                'teaching_days' => [6, 7],
                'day_start' => '08:00',
                'day_end' => '17:30',
                'period_minutes' => 90,
                'break_minutes' => 15,
                'lunch_start' => '12:30',
                'lunch_end' => '13:30',
                // Exams run longer than a period and need the hall turned
                // round between sittings, so they get their own window.
                'exam_days' => [6, 7],
                'exam_day_start' => '08:30',
                'exam_day_end' => '17:30',
                'exam_duration_minutes' => 180,
                'exam_gap_minutes' => 60,
                'exam_period_days' => 21,
            ],
            // Weekday late slots, after the working day.
            STUDY_MODE_EVENING => [
                'teaching_days' => [1, 2, 3, 4, 5],
                'day_start' => '17:30',
                'day_end' => '20:30',
                'period_minutes' => 90,
                'break_minutes' => 0,
                'lunch_start' => null,
                'lunch_end' => null,
                // Exams run longer than a period and need the hall turned
                // round between sittings, so they get their own window.
                'exam_days' => [1, 2, 3, 4, 5],
                'exam_day_start' => '17:30',
                'exam_day_end' => '20:30',
                'exam_duration_minutes' => 180,
                'exam_gap_minutes' => 0,
                'exam_period_days' => 14,
            ],
            STUDY_MODE_SUMMER => [
                'teaching_days' => [1, 2, 3, 4, 5, 6],
                'day_start' => '08:00',
                'day_end' => '16:00',
                'period_minutes' => 120,
                'break_minutes' => 15,
                'lunch_start' => '12:00',
                'lunch_end' => '13:00',
                // Exams run longer than a period and need the hall turned
                // round between sittings, so they get their own window.
                'exam_days' => [1, 2, 3, 4, 5, 6],
                'exam_day_start' => '08:00',
                'exam_day_end' => '16:00',
                'exam_duration_minutes' => 120,
                'exam_gap_minutes' => 60,
                'exam_period_days' => 10,
            ],
        ];

        foreach ($grids as $modeCode => $grid) {
            $modeId = LookupService::getValueByCode(STUDY_MODE, $modeCode, needId: true);
            if (!$modeId) {
                consoleError("ScheduleSettingSeeder: STUDY_MODE value [{$modeCode}] is missing — run LookupSeeder first.");
                continue;
            }

            $setting = ScheduleSetting::firstOrNew(['study_mode_lookup_value_id' => $modeId]);
            $setting->fill($grid + ['is_active' => true, 'user_id' => $user->id]);

            // DatabaseSeeder runs WithoutModelEvents — stamp uuid by hand.
            $setting->uuid ??= (string) Str::uuid();
            $setting->save();
        }
    }
}
