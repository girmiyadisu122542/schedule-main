<?php

namespace App\Services\Academic;

use App\Models\Academic\AcademicYear;
use Constants\AppConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicYearService {

    /**
     * Create an academic year.
     *
     * @param array $data validated request payload
     * @return \App\Models\Academic\AcademicYear|string
     */
    public function createAcademicYear(array $data) {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);
            $attributes['user_id'] = Auth::id();

            // Promoting a new current year demotes the incumbent — the partial
            // unique index would otherwise reject the insert.
            if ($attributes['is_current']) {
                $this->demoteCurrentYear();
            }

            $academicYear = AcademicYear::create($attributes);

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $academicYear;
    }

    /**
     * Update an academic year.
     *
     * @param \App\Models\Academic\AcademicYear $academicYear
     * @param array $data validated request payload
     *
     * @return \App\Models\Academic\AcademicYear|string
     */
    public function updateAcademicYear(AcademicYear $academicYear, array $data) {
        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            $attributes = $this->buildAttributes($data);

            if ($attributes['is_current']) {
                $this->demoteCurrentYear(exceptId: $academicYear->id);
            }

            $academicYear->fill($attributes);
            $academicYear->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();
            throw $exception;
        }

        return $academicYear->refresh();
    }

    /**
     * Map a validated payload onto model attributes.
     *
     * @param array $data validated request payload
     * @return array
     */
    private function buildAttributes(array $data): array {
        return [
            'code' => $data['code'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_current' => (bool) ($data['is_current'] ?? false),
        ];
    }

    /**
     * Clear the is_current flag from whichever year currently holds it.
     *
     * @param int|null $exceptId academic year to leave untouched (the one being updated)
     * @return void
     */
    private function demoteCurrentYear(?int $exceptId = null): void {
        AcademicYear::query()
            ->where('is_current', true)
            ->when($exceptId, fn ($query) => $query->whereNot('id', $exceptId))
            ->update(['is_current' => false]);
    }
}
