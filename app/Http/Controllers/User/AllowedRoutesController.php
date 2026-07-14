<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
