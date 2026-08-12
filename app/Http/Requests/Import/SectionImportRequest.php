<?php

namespace App\Http\Requests\Import;

class SectionImportRequest extends ImportRequest {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanImportSection();
    }
}
