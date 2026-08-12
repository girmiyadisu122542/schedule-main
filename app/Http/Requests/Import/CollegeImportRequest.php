<?php

namespace App\Http\Requests\Import;

class CollegeImportRequest extends ImportRequest {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanImportCollege();
    }
}
