<?php

use App\Constants\FrontendPaths;
use Helper\SideBar\SideBar;
use Helper\SideBar\SideBarItem;

return SideBar::create([
    SideBar::make(
        title: 'menu',
        order: 1,
        items: [
            SideBarItem::dashboard('grid', FrontendPaths::DASHBOARD),
        ]
    ),

    SideBar::make(
        title: 'access',
        order: 2,
        items: [
            SideBarItem::userAndAccess('UserCheckmarkIcon', null, [
                SideBarItem::manageUsers('UsersIcon', FrontendPaths::MANAGE_USERS),
                SideBarItem::manageRoles('UserProfileSettingIcon', FrontendPaths::MANAGE_ROLES),
                SideBarItem::managePermissions('ShieldProtectedCheckmarkIcon', FrontendPaths::MANAGE_PERMISSIONS),
            ]),
        ]
    ),

    SideBar::make(
        title: 'dynamicConfiguration',
        order: 3,
        items: [
            SideBarItem::dynamicValues('InvoiceIcon', FrontendPaths::MANAGE_DYNAMIC_VALUES),
        ]
    ),

    SideBar::make(
        title: 'profile',
        order: 4,
        items: [
            SideBarItem::userProfile('UsersIcon', FrontendPaths::MANAGE_PROFILE),
        ]
    ),
]);
