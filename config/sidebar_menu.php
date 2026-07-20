<?php

use App\Constants\FrontendPaths;
use Helper\SideBar\SideBar;
use Helper\SideBar\SideBarItem;

return SideBar::create([
    SideBar::make(
        title: '',
        order: 1,
        items: [
            SideBarItem::dashboard('grid', FrontendPaths::DASHBOARD),
            SideBarItem::configurations('Database', null, [
                SideBarItem::collegesOrSchools('BuildingCityIcon', FrontendPaths::COLLEGES),
                SideBarItem::departments('BuildingIcon', FrontendPaths::DEPARTMENTS),
                SideBarItem::instructors('UserIcon', FrontendPaths::INSTRUCTORS),
                SideBarItem::classes('BusinessChart', FrontendPaths::CLASSES),
                SideBarItem::courses('BookIcon', FrontendPaths::COURSES),
                SideBarItem::rooms('KeyIcon', FrontendPaths::ROOMS),
            ]),
            SideBarItem::scheduling('ClockTimeTimerArrow', null, [
                SideBarItem::classSchedules('Calendar', FrontendPaths::CLASS_SCHEDULES),
                SideBarItem::examSchedules('SquarePenIcon', FrontendPaths::EXAM_SCHEDULES),
            ]),
            SideBarItem::reports('FileText', FrontendPaths::REPORTS),
            SideBarItem::notifications('BellIcon', FrontendPaths::NOTIFICATIONS),
            SideBarItem::dynamicValues('InvoiceIcon', FrontendPaths::MANAGE_DYNAMIC_VALUES),

            SideBarItem::userAndAccess('Users', null, [
                SideBarItem::manageUsers('UsersIcon', FrontendPaths::MANAGE_USERS),
                SideBarItem::manageRoles('UserProfileSettingIcon', FrontendPaths::MANAGE_ROLES),
                SideBarItem::managePermissions('ShieldProtectedCheckmarkIcon', FrontendPaths::MANAGE_PERMISSIONS),
            ]),
        ]
    ),


]);
