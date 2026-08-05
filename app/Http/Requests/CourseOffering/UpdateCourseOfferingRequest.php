<?php

namespace App\Http\Requests\CourseOffering;

use Helper\Permission\PermissionAction;

/**
 * Same payload as create — the difference is the permission gate, and that the
 * service refuses to touch an offering already inside the approval chain.
 */
class UpdateCourseOfferingRequest extends StoreCourseOfferingRequest {
    use PermissionAction;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return $this->userCanUpdateCourseOffering();
    }
}
