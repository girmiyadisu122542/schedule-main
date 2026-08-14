<?php

namespace App\Http\Requests\Import;

class CourseOfferingImportRequest extends ImportRequest {

    /**
     * Determine if the user is authorized to make this request.
     *
     * The per-row department bound is checked by the column map, in pass 1, so a
     * row outside the uploader's scope is reported with its row and column
     * rather than failing the whole upload.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanImportCourseOffering();
    }
}
