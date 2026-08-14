<?php

namespace App\Http\Requests\CourseOffering;

use App\Models\Offering\CourseOffering;
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
     * Scope is checked at BOTH ends: the department the offering is in, and the
     * department the payload would move it to. Checking only the payload would
     * let a caller lift someone else's offering into their own department and
     * thereby acquire it.
     *
     * @return bool
     */
    public function authorize(): bool {
        if (!$this->userCanUpdateCourseOffering()) {
            return false;
        }

        $currentDepartmentId = CourseOffering::query()
            ->whereKey($this->route('id'))
            ->value('department_id');

        return $this->scopeAllowsAuthoring($currentDepartmentId ? (int) $currentDepartmentId : null)
            && $this->scopeAllowsAuthoring($this->integerOrNull('department_id'));
    }
}
