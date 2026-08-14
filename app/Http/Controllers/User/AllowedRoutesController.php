<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Academic\Department;
use App\Services\User\DepartmentScopeService;
use Common\Lang\Lang;
use Helper\Permission\PermissionActionHelper;
use Helper\Response\Response;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Translation\Message;

class AllowedRoutesController extends Controller {

    /**
     * Resolve the frontend routes, encrypted permission actions and the
     * sidebar menu the authenticated user is allowed to reach. Routes are
     * gated only by permission keys (flat RBAC) — a route is allowed when it
     * requires no permission or the user holds at least one of its keys.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        try {
            /** @var \App\Models\User */
            $user = auth(API_GUARD)->user();
            $allPermissions = $user?->getAllPermissions();
            $config = config('frontend_routes');
            $authRedirect = $config['auth_redirect'];
            $unauthRedirect = $config['unauth_redirect'];
            $routes = $config['routes'];
            $menu = config('sidebar_menu');

            $entitlements = [
                'is_active' => (bool) $user,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
                'trial' => null,
                'modules' => [],
                'features' => [],
                // Which departments this user owns. The scheduling screens read
                // it to pin their filters and to drop pickers that would only
                // ever offer one answer — the server enforces the same bound.
                'scope' => $this->departmentScope($user),
            ];

            if (empty($allPermissions)) {
                $publicRoutes = [];
                foreach ($routes as $path => $permissions) {
                    if (empty($permissions)) {
                        $publicRoutes[] = $path;
                    }
                }
                return Response::_200([
                    'routes' => $publicRoutes,
                    'actions' => [],
                    'sidebarMenu' => [],
                    'entitlements' => $entitlements,
                    'meters' => [],
                    'authRedirect' => $authRedirect,
                    'unauthRedirect' => $unauthRedirect,
                ]);
            }

            $allowedRoutes = [];
            foreach ($routes as $path => $permissions) {
                $hasPermission = empty($permissions) || count(array_intersect($permissions, $allPermissions)) > 0;
                if (!$hasPermission) {
                    continue;
                }

                $allowedRoutes[] = $path;
            }

            $filteredMenu = [];
            if (!empty($allowedRoutes)) {
                foreach ($menu as $group) {
                    $filteredItems = $this->filterMenuItems($group['items'], $allowedRoutes);
                    if (!empty($filteredItems)) {
                        $group['items'] = $filteredItems;
                        $filteredMenu[] = $group;
                    }
                }
                $filteredMenu = $this->translateMenu($filteredMenu);
            }
            $menu = $filteredMenu;

            $key = env('PERMISSION_ACTION_KEY');
            $encryptedActions = PermissionActionHelper::convertAndEncryptPermissions($allPermissions, $key);

            $data = [
                'routes' => $allowedRoutes,
                'actions' => $encryptedActions,
                'sidebarMenu' => $menu,
                'entitlements' => $entitlements,
                'meters' => [],
                'authRedirect' => $authRedirect,
                'unauthRedirect' => $unauthRedirect,
            ];

            return Response::_200($data);
        } catch (\Exception $exception) {
            return Response::_500(Message::get('internal_server_error') . ' ' . $exception->getMessage());
        }
    }

    /**
     * The departments this user is confined to, named for the UI.
     *
     * `unrestricted` is the important bit and is NOT the same as an empty list:
     * unrestricted means the whole institution, empty means nothing at all.
     *
     * @param \App\Models\User|null $user
     *
     * @return array{unrestricted: bool, departments: array}
     */
    private function departmentScope($user): array {
        $service = app(DepartmentScopeService::class);
        $ids = $service->departmentIds($user);
        $managedIds = $service->managedDepartmentIds($user);

        if ($ids === null) {
            return ['unrestricted' => true, 'departments' => [], 'managed_departments' => []];
        }

        return [
            'unrestricted' => false,
            // What the user may READ — includes the department they teach in.
            'departments' => $this->namedDepartments($ids),
            // What they may ACT FOR — heading it, or leading its college. The
            // invigilation screens key off this: answering a registrar on a
            // department's behalf is the head's job, not every teacher's.
            'managed_departments' => $this->namedDepartments($managedIds ?? []),
        ];
    }

    /**
     * Departments by id, in the compact shape the frontend renders.
     *
     * @param array<int, int> $ids
     * @return array
     */
    private function namedDepartments(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        return Department::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Department $department) => $department->resource('idAndNameFields'))
            ->all();
    }

    /**
     * Recursively filter menu items based on allowed routes
     *
     * @param array $items
     * @param array $allowedRoutes
     *
     * @return array
     */
    private function filterMenuItems($items, $allowedRoutes) {
        $filteredItems = [];
        foreach ($items as $item) {
            $filteredItem = $item;
            $hasAccess = false;

            if (isset($item['path']) && in_array($item['path'], $allowedRoutes)) {
                $hasAccess = true;
            }

            if (isset($item['subItems'])) {
                $filteredSubItems = $this->filterMenuItems($item['subItems'], $allowedRoutes);
                if (!empty($filteredSubItems)) {
                    $filteredItem['subItems'] = $filteredSubItems;
                    $hasAccess = true;
                } else {
                    unset($filteredItem['subItems']);
                }
            }

            if ($hasAccess) {
                $filteredItems[] = $filteredItem;
            }
        }

        return $filteredItems;
    }

    /**
     * Translate sidebar menu names using BackLang
     *
     * @param array $menu
     *
     * @return array
     */
    private function translateMenu($menu) {
        $sidebarTranslations = Lang::getSidebarMergedLanguage();

        foreach ($menu as &$group) {
            if (isset($sidebarTranslations[$group['title']])) {
                $group['title'] = $sidebarTranslations[$group['title']];
            }
            $group['items'] = $this->translateMenuItems($group['items'], $sidebarTranslations);
        }

        return $menu;
    }

    /**
     * Recursively translate menu item names
     *
     * @param array $items
     * @param array $translations
     *
     * @return array
     */
    private function translateMenuItems($items, $translations) {
        foreach ($items as &$item) {
            if (isset($translations[$item['name']])) {
                $item['name'] = $translations[$item['name']];
            }
            if (isset($item['subItems'])) {
                $item['subItems'] = $this->translateMenuItems($item['subItems'], $translations);
            }
        }

        return $items;
    }
}
