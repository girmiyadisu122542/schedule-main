<?php

namespace App\Http\Requests\CourseOffering;

use App\Models\Offering\CourseOffering;
use Helper\Permission\PermissionAction;
use Illuminate\Auth\Access\AuthorizationException;
use Translation\Message;

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

    /**
     * Say WHICH of the two checks refused — see the note on the store request.
     * A missing permission and an out-of-scope department are different
     * problems with different fixes, and one sentence for both hides that.
     *
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function failedAuthorization(): void {
        throw new AuthorizationException(
            $this->userCanUpdateCourseOffering()
                ? Message::get('offering_department_out_of_scope')
                : Message::get('unauthorized_action')
        );
    }

}
