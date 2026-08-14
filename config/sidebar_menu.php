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
                SideBarItem::campuses('MapPin', FrontendPaths::CAMPUSES),
                SideBarItem::buildings('ModernBuilding', FrontendPaths::BUILDINGS),
                SideBarItem::rooms('KeyIcon', FrontendPaths::ROOMS),
                SideBarItem::collegesOrSchools('BuildingCityIcon', FrontendPaths::COLLEGES),
                SideBarItem::departments('BuildingIcon', FrontendPaths::DEPARTMENTS),
                SideBarItem::programs('BookIcon', FrontendPaths::PROGRAMS),
                SideBarItem::academicYears('Calendar', FrontendPaths::ACADEMIC_YEARS),
                SideBarItem::semesters('ClockTimeTimerArrow', FrontendPaths::SEMESTERS),
                SideBarItem::instructors('UserIcon', FrontendPaths::INSTRUCTORS),
                SideBarItem::sections('BusinessChart', FrontendPaths::SECTIONS),
                SideBarItem::courses('BookIcon', FrontendPaths::COURSES),
                // The generation grid: teaching days, day window, period
                // length and lunch — one per study mode.
                SideBarItem::scheduleSettings('ClockTimeTimerArrow', FrontendPaths::SCHEDULE_SETTINGS),
            ]),
            SideBarItem::courseOfferings('FileText', FrontendPaths::OFFERINGS),
            SideBarItem::scheduling('ClockTimeTimerArrow', null, [
                SideBarItem::classSchedules('Calendar', FrontendPaths::CLASS_SCHEDULES),
                SideBarItem::examSchedules('SquarePenIcon', FrontendPaths::EXAM_SCHEDULES),
                SideBarItem::timetable('grid', FrontendPaths::TIMETABLE),
                SideBarItem::examCalendar('date', FrontendPaths::EXAM_CALENDAR),
            ]),
            SideBarItem::invigilation('ShieldCheckAltIcon', null, [
                SideBarItem::invigilationRequests('SendPlaneIcon', FrontendPaths::INVIGILATION_REQUESTS),
                SideBarItem::invigilatorAssignments('ShieldCheckAltIcon', FrontendPaths::INVIGILATOR_ASSIGNMENTS),
            ]),
            SideBarItem::reports('FileText', FrontendPaths::REPORTS),
            // Notifications is not built yet (audit C28). A menu item that
            // leads to a placeholder teaches users the software is unfinished
            // and they generalise that to the parts that do work — so it stays
            // out of the sidebar until there is something behind it.
            SideBarItem::dynamicValues('InvoiceIcon', FrontendPaths::MANAGE_DYNAMIC_VALUES),

            SideBarItem::userAndAccess('Users', null, [
                SideBarItem::manageUsers('UsersIcon', FrontendPaths::MANAGE_USERS),
                SideBarItem::manageRoles('UserProfileSettingIcon', FrontendPaths::MANAGE_ROLES),
                SideBarItem::managePermissions('ShieldProtectedCheckmarkIcon', FrontendPaths::MANAGE_PERMISSIONS),
            ]),
        ]
    ),


]);
