<?php

namespace App\Services\Invigilation;

use App\Models\Academic\Semester;
use App\Models\Invigilation\InvigilatorAvailability;
use App\Models\People\Instructor;
use Constants\AppConstant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvigilatorAvailabilityService {

    /**
     * Which constraint maps to which error key. Overlap detection is the
     * database's job (`ia_no_overlap`); this service's job is to turn the
     * resulting QueryException back into something a user can read.
     *
     * The composite unique and the EXCLUDE overlap the same ground — an exact
     * duplicate trips whichever PostgreSQL checks first — so both land on one
     * message rather than splitting hairs the submitter does not care about.
     *
     * @var array<string, string>
     */
    public const CONFLICT_KEYS = [
        'ia_no_overlap' => 'availability_window_overlaps',
        'invigilator_availabilities_instructor_id_available_date' => 'availability_window_overlaps',
    ];

    /**
     * Record one availability window.
     *
     * @param array $data validated request payload
     * @return \App\Models\Invigilation\InvigilatorAvailability|string
     */
    public function createAvailability(array $data) {
        // ---- pre-flight checks (NO writes yet) ----
        $guard = $this->guardInputs($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['submitted_by_id'] = Auth::id();

            $availability = InvigilatorAvailability::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (QueryException $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $conflict = static::conflictKey($exception);
            if (!$conflict) {
                throw $exception;
            }

            return $conflict;
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $availability;
    }

    /**
     * The error key for a conflict the database refused, or null when the
     * QueryException was something else entirely (and must be rethrown).
     *
     * @param \Illuminate\Database\QueryException $exception
     * @return string|null
     */
    public static function conflictKey(QueryException $exception): ?string {
        foreach (static::CONFLICT_KEYS as $constraint => $key) {
            if (str_contains($exception->getMessage(), $constraint)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Business rules the foreign keys cannot express.
     *
     * @param array $data validated request payload
     * @return string|null an error translation key, or null when the input is fine
     */
    private function guardInputs(array $data): ?string {
        $instructor = Instructor::find((int) $data['instructor_id']);
        if (!$instructor?->can_invigilate || !$instructor->is_active) {
            return 'instructor_cannot_invigilate';
        }

        // A window outside the semester it is offered for cannot be used by
        // anything — every exam that could consume it sits inside the term.
        $semester = Semester::find((int) $data['semester_id']);
        if ($semester) {
            $date = Carbon::parse($data['available_date']);
            $withinTerm = $date->betweenIncluded(
                Carbon::parse($semester->start_date),
                Carbon::parse($semester->end_date),
            );

            if (!$withinTerm) {
                return 'availability_is_outside_the_semester';
            }
        }

        return null;
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @return array
     */
    private function buildAttributes(array $data): array {
        return [
            'instructor_id' => (int) $data['instructor_id'],
            'semester_id' => (int) $data['semester_id'],
            'available_date' => $data['available_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'remark' => $data['remark'] ?? null,
        ];
    }
}
