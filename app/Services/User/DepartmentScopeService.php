<?php

namespace App\Services\User;

use App\Models\Academic\College;
use App\Models\Academic\Department;
use App\Models\People\Instructor;
use App\Models\User;
use Helper\Permission\PermissionAction;
use Illuminate\Support\Facades\Auth;

/**
 * Which departments a user may see and act on.
 *
 * The institution is organised by college › department, and a department head
 * has no business editing another department's timetable. The permission system
 * answers "may this user publish a schedule at all"; this answers "whose".
 *
 * The binding is derived, not stored twice: a user reaches a department by
 * being its head, by leading the college above it, or by being an instructor in
 * it. Those columns already exist and are already maintained on the master-data
 * screens (Final Schema.md §3, §4, §11).
 *
 * Note on `head_user_id` / `dean_user_id`: the schema calls them routing
 * pointers and says they are NOT the authorization source, which stands — they
 * do not decide WHETHER a user may act. Whether the Dean role carries
 * `publish:class:schedule` is still an RBAC question. They decide only WHICH
 * rows the answer applies to.
 */
class DepartmentScopeService {

    use PermissionAction;

    /**
     * Resolved scopes for this request, keyed by user id.
     *
     * A list endpoint asks once for the query and once per authorization check;
     * without this that is three round trips to the same three tables.
     *
     * @var array<int, array<int, int>|null>
     */
    private array $cache = [];

    /**
     * The department ids a user is confined to.
     *
     * @param \App\Models\User|null $user defaults to the authenticated user
     *
     * @return array<int, int>|null NULL means unrestricted — see everything.
     *                              An EMPTY array means the user is bound to no
     *                              department and so sees nothing.
     */
    public function departmentIds(?User $user = null): ?array {
        $user ??= Auth::user();

        if (!$user) {
            return [];
        }

        if (array_key_exists($user->id, $this->cache)) {
            return $this->cache[$user->id];
        }

        return $this->cache[$user->id] = $this->resolve($user);
    }

    /** Whether this user sees the whole institution. */
    public function isUnrestricted(?User $user = null): bool {
        return $this->departmentIds($user) === null;
    }

    /**
     * The departments a user MANAGES, as opposed to merely reads.
     *
     * Reading and managing are not the same claim: an instructor may see their
     * own department's timetable, but answering a registrar's request on that
     * department's behalf is the head's job. Folding the two together let any
     * teacher speak for their department, which is why this exists separately.
     *
     * Heading a department, or leading the college above it, is what counts —
     * teaching in one is not.
     *
     * @param \App\Models\User|null $user
     *
     * @return array<int, int>|null NULL means unrestricted; an EMPTY array
     *                              means the user manages nothing.
     */
    public function managedDepartmentIds(?User $user = null): ?array {
        $user ??= Auth::user();

        if (!$user) {
            return [];
        }

        $cacheKey = 'managed:' . $user->id;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if ($user->isSuperAdmin() || $this->userCan(PERMISSION_SEE_ALL_DEPARTMENTS, $user)) {
            return $this->cache[$cacheKey] = null;
        }

        $ledCollegeIds = College::query()
            ->where('dean_user_id', $user->id)
            ->pluck('id')
            ->all();

        $ids = Department::query()
            ->where('head_user_id', $user->id)
            ->when($ledCollegeIds, fn ($query) => $query->orWhereIn('college_id', $ledCollegeIds))
            ->pluck('id')
            ->all();

        return $this->cache[$cacheKey] = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Whether this user may act FOR a department, not merely read it.
     *
     * @param int|null $departmentId
     * @param \App\Models\User|null $user
     *
     * @return bool
     */
    public function manages(?int $departmentId, ?User $user = null): bool {
        return self::permits($this->managedDepartmentIds($user), $departmentId);
    }

    /**
     * The colleges a user LEADS as dean.
     *
     * Separate from {@see self::managedDepartmentIds()}, which deliberately
     * folds a dean in with the heads of the departments beneath them. That is
     * the right answer for "may they act for this department", but the wrong one
     * for the offering chain's COLLEGE tier: a department head must not be able
     * to take the college decision on their own department's work, which is
     * exactly what a folded-together scope would allow.
     *
     * @param \App\Models\User|null $user
     *
     * @return array<int, int>|null NULL means unrestricted; an EMPTY array means
     *                              the user leads no college.
     */
    public function deanedCollegeIds(?User $user = null): ?array {
        $user ??= Auth::user();

        if (!$user) {
            return [];
        }

        $cacheKey = 'deaned:' . $user->id;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if ($user->isSuperAdmin() || $this->userCan(PERMISSION_SEE_ALL_DEPARTMENTS, $user)) {
            return $this->cache[$cacheKey] = null;
        }

        $ids = College::query()
            ->where('dean_user_id', $user->id)
            ->pluck('id')
            ->all();

        return $this->cache[$cacheKey] = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Whether this user leads the college a department sits in.
     *
     * @param int|null $departmentId
     * @param \App\Models\User|null $user
     *
     * @return bool
     */
    public function leadsCollegeOf(?int $departmentId, ?User $user = null): bool {
        $scope = $this->deanedCollegeIds($user);

        if ($scope === null) {
            return true;
        }

        if ($departmentId === null || !$scope) {
            return false;
        }

        $collegeId = Department::query()->whereKey($departmentId)->value('college_id');

        return $collegeId !== null && in_array((int) $collegeId, $scope, true);
    }

    /**
     * Whether a specific department is inside the user's scope.
     *
     * @param int|null $departmentId
     * @param \App\Models\User|null $user
     *
     * @return bool
     */
    public function allows(?int $departmentId, ?User $user = null): bool {
        return self::permits($this->departmentIds($user), $departmentId);
    }

    /**
     * Whether a scope admits a department. Pure — the decision, with no lookup.
     *
     * Split out from `allows()` so the rule can be tested without a database:
     * the fetching is what needs one, and the fetching is not the interesting
     * part.
     *
     * @param array<int, int>|null $scope null = unrestricted, [] = nothing
     * @param int|null $departmentId
     *
     * @return bool
     */
    public static function permits(?array $scope, ?int $departmentId): bool {
        if ($scope === null) {
            return true;
        }

        // A row that belongs to no department is nobody's to touch except an
        // unrestricted user's.
        return $departmentId !== null && in_array($departmentId, $scope, true);
    }

    /**
     * Fold the three routes into one deduplicated department list. Pure.
     *
     * @param array<int, mixed> $headDepartmentIds departments the user heads,
     *                                             already including any reached
     *                                             through a led college
     * @param int|null $instructorDepartmentId the user's own teaching department
     *
     * @return array<int, int>
     */
    public static function combine(array $headDepartmentIds, ?int $instructorDepartmentId): array {
        if ($instructorDepartmentId) {
            $headDepartmentIds[] = $instructorDepartmentId;
        }

        return array_values(array_unique(array_map('intval', $headDepartmentIds)));
    }

    /**
     * Work out the scope from scratch.
     *
     * @param \App\Models\User $user
     * @return array<int, int>|null
     */
    private function resolve(User $user): ?array {
        // The whole institution: super admins, and whichever role the
        // institution gives the registrar's cross-department view to.
        if ($user->isSuperAdmin() || $this->userCan(PERMISSION_SEE_ALL_DEPARTMENTS, $user)) {
            return null;
        }

        $ledCollegeIds = College::query()
            ->where('dean_user_id', $user->id)
            ->pluck('id')
            ->all();

        $ids = Department::query()
            ->where('head_user_id', $user->id)
            // A dean covers every department in their college.
            ->when($ledCollegeIds, fn ($query) => $query->orWhereIn('college_id', $ledCollegeIds))
            ->pluck('id')
            ->all();

        // An instructor's own department — the teaching staff's read access to
        // the timetable they appear on.
        $instructorDepartmentId = Instructor::query()
            ->where('user_id', $user->id)
            ->value('department_id');

        return self::combine($ids, $instructorDepartmentId ? (int) $instructorDepartmentId : null);
    }
}
