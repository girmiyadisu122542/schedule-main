<?php

namespace Translation\Sidebar;

use Common\Lang\Lang;

class Amharic extends Lang {

    protected static $key = 'am';
    protected static $name = 'amharic';
    protected static $icon = 'et.png';

    /**
     * The language translations
     *
     * @return array<string, string>
     */
    public static function translations(): array {
        return [
            'menu' => 'መነሻ',
            'dashboard' => 'ዳሽቦርድ',

            // permissions sidebar items
            'accessManagement' => 'መግቢያ ማስተዳደሪያ',
            'permissions' => 'ፈቃዶች',
            'createPermissionGroups' => 'የፈቃድ ቡድኖችን ፍጠር',
            'permissionGroups' => "የፈቃድ ቡድኖች",
            'manageUsers' => 'ተጠቃሚ',
            'manageRoles' => 'ሚና',
            'managePermissions' => 'ፈቃዶች',
            'access' => 'መግቢያ',
            'userPermissionOverride' => 'የተጠቃሚ ፈቃድ ማሻሻያ',

            //role
            'roles' => 'ሚናዎች',
            'viewRoles' => 'ሚናዎችን ይመልከቱ',
            'createRole' => 'ሚና ፍጠር',
            'editRole' => 'ሚና አርትዕ',
            'assignRole' => 'ሚና መድብ',

            'commons' => 'የጋራ',

            // For Test
            'administrativeStructure' => 'የአስተዳደር መዋቅር',

            // company setup related items
            'companySetup' => 'የኩባንያ ማዋቀሪያ',
            'systemSetup' => 'የስርዓት ማዋቀሪያ',
            'userAndAccess' => 'ተጠቃሚ እና አስተዳደር',
            'adminSetup' => 'የአስተዳደር ማዋቀሪያ',

            // dynamic configuration related items
            'dynamicConfiguration' => 'ተለዋዋጭ ውቅር',
            'profile' => 'መገለጫ',
            'userProfile' => 'የተጠቃሚ መገለጫ',
            'dynamicValues' => 'ተለዋዋጭ እሴቶች',

            // measurement related items
            'measurement' => 'መለኪያ',


            // customer related items
            'crmSetup' => 'የደንበኛ ማዋቀሪያ',
            'customer' => 'ደንበኛ',
            'supplier' => 'አቅራቢ',
            'employee' => 'ሰራተኛ',

            'configurations' => 'ማዋቀሪያዎች',
            'campuses' => 'ካምፓሶች',
            'buildings' => 'ሕንፃዎች',
            'collegesOrSchools' => 'ኮሌጆች/ትምህርት ቤቶች',
            'academicYears' => 'የትምህርት ዓመታት',
            'programs' => 'ፕሮግራሞች',
            'semesters' => 'ሴሚስተሮች',
            'departments' => 'ዲፓርትመንቶች',
            'instructors' => 'አስተማሪዎች',
            'sections' => 'ክፍሎች',
            'courses' => 'ኮርሶች',
            'rooms' => 'የመማሪያ ክፍሎች',
            'courseOfferings' => 'የኮርስ አቅርቦቶች',
            'scheduling' => 'ፕሮግራም ማውጣት',
            'classSchedules' => 'የክፍል ፕሮግራሞች',
            'invigilation' => 'የፈተና ቁጥጥር',
            'invigilatorAvailabilities' => 'የተቆጣጣሪ ዝግጁነት',
            'invigilatorAssignments' => 'የቁጥጥር ምድብ',
            'timetable' => 'መርሃ ግብር',
            'examCalendar' => 'የፈተና የቀን መቁጠሪያ',
            'examSchedules' => 'የፈተና ፕሮግራሞች',
            'reports' => 'ሪፖርቶች',
            'notifications' => 'ማሳወቂያዎች',
            // subscription and pricing related items
        ];
    }
}
