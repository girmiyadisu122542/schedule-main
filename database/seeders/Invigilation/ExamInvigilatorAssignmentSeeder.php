<?php

namespace Database\Seeders\Invigilation;

use App\Models\Invigilation\ExamInvigilatorAssignment;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Models\Schedule\ExamSchedule;
use App\Models\User;
use App\Services\Lookup\LookupService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;

class ExamInvigilatorAssignmentSeeder extends Seeder {

    /**
     * Staff the seeded sittings from the offered availability windows, so the
     * duty roster screen has something real to show.
     *
     * This deliberately does NOT call the auto-assign service: a seeder has no
     * authenticated user, and `assigned_by_id` is not nullable. It writes the
     * same shape of rows, drawing on the same windows.
     *
     * @return void
     */
    public function run(): void {
        $user = User::first();
        if (!$user) {
            consoleError('ExamInvigilatorAssignmentSeeder cannot proceed: no user found.');
            return;
        }

        $assignedId = LookupService::getValueByCode(INVIGILATION_STATUS, INVIGILATION_STATUS_ASSIGNED, needId: true);
        $chiefId = LookupService::getValueByCode(INVIGILATOR_ROLE, INVIGILATOR_ROLE_CHIEF, needId: true);
        $assistantId = LookupService::getValueByCode(INVIGILATOR_ROLE, INVIGILATOR_ROLE_ASSISTANT, needId: true);

        if (!$assignedId || !$chiefId || !$assistantId) {
            consoleError('ExamInvigilatorAssignmentSeeder cannot proceed: INVIGILATION_STATUS / INVIGILATOR_ROLE lookup values are missing.');
            return;
        }

        $exams = ExamSchedule::query()->where('state', STATE_ACTIVE)->orderBy('exam_date')->orderBy('start_time')->get();
        if ($exams->isEmpty()) {
            // Nothing to staff is not an error — the exam seeder is optional.
            return;
        }

        foreach ($exams as $exam) {
            // Containment, not overlap: a window that only half covers the
            // sitting is no use to it.
            $candidates = InvigilatorAvailability::query()
                ->where('available_date', $exam->exam_date)
                ->where('start_time', '<=', $exam->start_time)
                ->where('end_time', '>=', $exam->end_time)
                ->whereHas('instructor', fn ($query) => $query->where('can_invigilate', true)->where('is_active', true))
                ->orderBy('instructor_id')
                ->pluck('instructor_id')
                ->unique()
                ->values();

            $placed = 0;
            foreach ($candidates as $instructorId) {
                if ($placed >= $exam->required_invigilators) {
                    break;
                }

                // Each row commits on its own: a double-booking rejection means
                // that person is busy, and the rest of the roster is still good.
                try {
                    $row = ExamInvigilatorAssignment::firstOrNew([
                        'exam_schedule_id' => $exam->id,
                        'instructor_id' => $instructorId,
                    ]);
                    $row->fill([
                        'exam_date' => $exam->exam_date,
                        'start_time' => $exam->start_time,
                        'end_time' => $exam->end_time,
                        'role_lookup_value_id' => $placed === 0 ? $chiefId : $assistantId,
                        'status_lookup_value_id' => $assignedId,
                        'state' => STATE_ACTIVE,
                        'assigned_by_id' => $user->id,
                        'assigned_at' => now(),
                    ]);
                    $row->save();
                    $placed++;
                } catch (QueryException $exception) {
                    // Already on duty elsewhere at this time — try the next.
                    continue;
                }
            }
        }
    }
}
