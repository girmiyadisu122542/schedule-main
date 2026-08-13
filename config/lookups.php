<?php

return [
    // User Status Lookup
    [
        'name' => 'userStatus',
        'code' => USER_STATUS,
        'description' => 'userStatusDesc',
        'applies_to_model' => [MODEL_USER],
        'is_system' => true,
        'values' => [
            ['name' => 'active', 'code' => USER_STATUS_ACTIVE, 'order' => 1, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => 'inactive', 'code' => USER_STATUS_INACTIVE, 'order' => 2, 'color' => '#6B7280', 'icon' => 'x-circle'],
            ['name' => 'pending', 'code' => USER_STATUS_PENDING_VERIFICATION, 'order' => 5, 'color' => '#F59E0B', 'icon' => 'clock'],
        ],
        'transitions' => [
            ['from' => USER_STATUS_PENDING_VERIFICATION, 'to' => USER_STATUS_ACTIVE],
            ['from' => USER_STATUS_ACTIVE, 'to' => USER_STATUS_INACTIVE],
            ['from' => USER_STATUS_INACTIVE, 'to' => USER_STATUS_ACTIVE],
        ],
    ],

    // Generic Status (can be used for multiple models)
    [
        'name' => 'genericStatus',
        'code' => GENERIC_STATUS,
        'description' => 'genericStatusDesc',
        'applies_to_model' => [],
        'is_system' => false,
        'values' => [
            ['name' => 'active', 'code' => USER_STATUS_ACTIVE, 'order' => 1, 'color' => '#10B981'],
            ['name' => 'inactive', 'code' => USER_STATUS_INACTIVE, 'order' => 2, 'color' => '#6B7280'],
        ],
        'transitions' => [
            ['from' => USER_STATUS_ACTIVE, 'to' => USER_STATUS_INACTIVE],
            ['from' => USER_STATUS_INACTIVE, 'to' => USER_STATUS_ACTIVE],
        ],
    ],

    // Lookup value status (drives the status-based query engine)
    [
        'name' => 'lookupValueStatus',
        'code' => LOOKUP_VALUE_STATUS,
        'description' => 'lookupValueStatusDesc',
        'applies_to_model' => [
            MODEL_LOOKUP_VALUE,
        ],
        'is_system' => true,
        'values' => [
            ['name' => 'pending', 'code' => LOOKUP_VALUE_STATUS_PENDING, 'order' => 1, 'color' => '#F59E0B', 'icon' => 'clock'],
            ['name' => 'acceptForAll', 'code' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL, 'order' => 2, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => 'acceptForThis', 'code' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS, 'order' => 3, 'color' => '#3B82F6', 'icon' => 'check-circle'],
            ['name' => 'reject', 'code' => LOOKUP_VALUE_STATUS_REJECT, 'order' => 4, 'color' => '#EF4444', 'icon' => 'x-circle'],
        ],
        'transitions' => [
            ['from' => LOOKUP_VALUE_STATUS_PENDING, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL],
            ['from' => LOOKUP_VALUE_STATUS_PENDING, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS],
            ['from' => LOOKUP_VALUE_STATUS_PENDING, 'to' => LOOKUP_VALUE_STATUS_REJECT],

            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL],
            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS],

            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS, 'to' => LOOKUP_VALUE_STATUS_REJECT],
            ['from' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL, 'to' => LOOKUP_VALUE_STATUS_REJECT],

            ['from' => LOOKUP_VALUE_STATUS_REJECT, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL],
            ['from' => LOOKUP_VALUE_STATUS_REJECT, 'to' => LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS],
        ],
    ],
    /**
     * ---------------------------------------------------------------------
     * Scheduling vocabularies — Final Schema.md § "Lookup vocabularies"
     * and Final Schema with example.md § "LOOKUP SEED DATA".
     *
     * Names are given as explicit {en, am} maps (LookupSeeder::resolveTranslation
     * passes an array straight through), so the catalogue reads in one place
     * instead of scattering ~60 keys across the Back translation files.
     *
     * The four lifecycle types (SEMESTER_STATUS, COURSE_OFFERING_STATUS,
     * CLASS_SCHEDULE_STATUS, EXAM_SCHEDULE_STATUS) also declare `transitions`;
     * the existing lookup engine turns them into `lookup_transitions` rows that
     * guard every status move.
     * ---------------------------------------------------------------------
     */
    [
        'name' => ['en' => 'Degree Level', 'am' => 'የዲግሪ ደረጃ'],
        'code' => DEGREE_LEVEL,
        'description' => ['en' => 'Academic award level a program leads to', 'am' => 'አንድ ፕሮግራም የሚያደርስበት የትምህርት ደረጃ'],
        'applies_to_model' => [MODEL_PROGRAM],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Certificate', 'am' => 'ሰርተፍኬት'], 'code' => DEGREE_LEVEL_CERTIFICATE, 'order' => 1, 'color' => '#6B7280'],
            ['name' => ['en' => 'Diploma', 'am' => 'ዲፕሎማ'], 'code' => DEGREE_LEVEL_DIPLOMA, 'order' => 2, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Bachelor', 'am' => 'ባችለር'], 'code' => DEGREE_LEVEL_BACHELOR, 'order' => 3, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Master', 'am' => 'ማስተርስ'], 'code' => DEGREE_LEVEL_MASTER, 'order' => 4, 'color' => '#7C3AED'],
            ['name' => ['en' => 'PhD', 'am' => 'ዶክትሬት'], 'code' => DEGREE_LEVEL_PHD, 'order' => 5, 'color' => '#9333EA'],
        ],
    ],

    [
        'name' => ['en' => 'Study Mode', 'am' => 'የትምህርት ዘዴ'],
        'code' => STUDY_MODE,
        'description' => [
            'en' => 'How a program is delivered — decides which days and hours it may be scheduled in',
            'am' => 'አንድ ፕሮግራም የሚሰጥበት መንገድ — በየትኞቹ ቀናትና ሰዓታት እንደሚመደብ ይወስናል',
        ],
        'applies_to_model' => [MODEL_PROGRAM],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Regular', 'am' => 'መደበኛ'], 'code' => STUDY_MODE_REGULAR, 'order' => 1, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Extension', 'am' => 'ኤክስቴንሽን'], 'code' => STUDY_MODE_EXTENSION, 'order' => 2, 'color' => '#7C3AED'],
            ['name' => ['en' => 'Evening', 'am' => 'ማታ'], 'code' => STUDY_MODE_EVENING, 'order' => 3, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Summer', 'am' => 'የበጋ'], 'code' => STUDY_MODE_SUMMER, 'order' => 4, 'color' => '#F59E0B'],
            ['name' => ['en' => 'Distance', 'am' => 'የርቀት'], 'code' => STUDY_MODE_DISTANCE, 'order' => 5, 'color' => '#14B8A6'],
        ],
    ],

    [
        'name' => ['en' => 'Academic Rank', 'am' => 'የትምህርት ማዕረግ'],
        'code' => ACADEMIC_RANK,
        'description' => ['en' => 'Academic ladder position an instructor holds', 'am' => 'መምህሩ የያዘው የትምህርት ማዕረግ ደረጃ'],
        'applies_to_model' => [MODEL_INSTRUCTOR],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Graduate Assistant', 'am' => 'ምሩቅ ረዳት'], 'code' => ACADEMIC_RANK_GRADUATE_ASSISTANT, 'order' => 1, 'color' => '#6B7280'],
            ['name' => ['en' => 'Assistant Lecturer', 'am' => 'ረዳት መምህር'], 'code' => ACADEMIC_RANK_ASSISTANT_LECTURER, 'order' => 2, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Lecturer', 'am' => 'መምህር'], 'code' => ACADEMIC_RANK_LECTURER, 'order' => 3, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Assistant Professor', 'am' => 'ረዳት ፕሮፌሰር'], 'code' => ACADEMIC_RANK_ASSISTANT_PROFESSOR, 'order' => 4, 'color' => '#7C3AED'],
            ['name' => ['en' => 'Associate Professor', 'am' => 'ተባባሪ ፕሮፌሰር'], 'code' => ACADEMIC_RANK_ASSOCIATE_PROFESSOR, 'order' => 5, 'color' => '#9333EA'],
            ['name' => ['en' => 'Professor', 'am' => 'ፕሮፌሰር'], 'code' => ACADEMIC_RANK_PROFESSOR, 'order' => 6, 'color' => '#DB2777'],
            // A lab technician who only invigilates sits outside the teaching ladder.
            ['name' => ['en' => 'Technical Assistant', 'am' => 'የቴክኒክ ረዳት'], 'code' => ACADEMIC_RANK_TECHNICAL_ASSISTANT, 'order' => 7, 'color' => '#14B8A6'],
        ],
    ],

    [
        'name' => ['en' => 'Semester Status', 'am' => 'የሴሚስተር ሁኔታ'],
        'code' => SEMESTER_STATUS,
        'description' => ['en' => 'Where a semester sits in the scheduling cycle', 'am' => 'ሴሚስተሩ በፕሮግራም ዑደት ውስጥ ያለበት ደረጃ'],
        'applies_to_model' => [MODEL_SEMESTER],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Planning', 'am' => 'እቅድ'], 'code' => SEMESTER_STATUS_PLANNING, 'order' => 1, 'color' => '#6B7280', 'icon' => 'clipboard', 'is_default' => true],
            ['name' => ['en' => 'Scheduling', 'am' => 'ፕሮግራም በማውጣት ላይ'], 'code' => SEMESTER_STATUS_SCHEDULING, 'order' => 2, 'color' => '#F59E0B', 'icon' => 'calendar'],
            ['name' => ['en' => 'Active', 'am' => 'ንቁ'], 'code' => SEMESTER_STATUS_ACTIVE, 'order' => 3, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => ['en' => 'Closed', 'am' => 'ተዘግቷል'], 'code' => SEMESTER_STATUS_CLOSED, 'order' => 4, 'color' => '#374151', 'icon' => 'lock'],
        ],
        'transitions' => [
            ['from' => SEMESTER_STATUS_PLANNING, 'to' => SEMESTER_STATUS_SCHEDULING],
            ['from' => SEMESTER_STATUS_SCHEDULING, 'to' => SEMESTER_STATUS_ACTIVE],
            ['from' => SEMESTER_STATUS_ACTIVE, 'to' => SEMESTER_STATUS_CLOSED],
        ],
    ],

    [
        'name' => ['en' => 'Room Type', 'am' => 'የክፍል ዓይነት'],
        'code' => ROOM_TYPE,
        'description' => ['en' => 'What a bookable venue is built for', 'am' => 'የሚያዝ ክፍል የተሰራበት ዓላማ'],
        'applies_to_model' => [MODEL_ROOM],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Lecture Hall', 'am' => 'የንባብ አዳራሽ'], 'code' => ROOM_TYPE_LECTURE_HALL, 'order' => 1, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Laboratory', 'am' => 'ላብራቶሪ'], 'code' => ROOM_TYPE_LAB, 'order' => 2, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Seminar Room', 'am' => 'የሴሚናር ክፍል'], 'code' => ROOM_TYPE_SEMINAR_ROOM, 'order' => 3, 'color' => '#14B8A6'],
            ['name' => ['en' => 'Workshop', 'am' => 'ወርክሾፕ'], 'code' => ROOM_TYPE_WORKSHOP, 'order' => 4, 'color' => '#F59E0B'],
            ['name' => ['en' => 'Auditorium', 'am' => 'ኦዲቶሪየም'], 'code' => ROOM_TYPE_AUDITORIUM, 'order' => 5, 'color' => '#7C3AED'],
            ['name' => ['en' => 'Exam Hall', 'am' => 'የፈተና አዳራሽ'], 'code' => ROOM_TYPE_EXAM_HALL, 'order' => 6, 'color' => '#EF4444'],
        ],
    ],

    [
        'name' => ['en' => 'Course Type', 'am' => 'የኮርስ ዓይነት'],
        'code' => COURSE_TYPE,
        'description' => ['en' => 'How a catalogue course is delivered', 'am' => 'ኮርሱ የሚሰጥበት መንገድ'],
        'applies_to_model' => [MODEL_COURSE],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Lecture', 'am' => 'ንባብ'], 'code' => COURSE_TYPE_LECTURE, 'order' => 1, 'color' => '#2563EB'],
            ['name' => ['en' => 'Laboratory', 'am' => 'ላብራቶሪ'], 'code' => COURSE_TYPE_LAB, 'order' => 2, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Lecture + Lab', 'am' => 'ንባብ እና ላብራቶሪ'], 'code' => COURSE_TYPE_LECTURE_LAB, 'order' => 3, 'color' => '#14B8A6', 'is_default' => true],
            ['name' => ['en' => 'Seminar', 'am' => 'ሴሚናር'], 'code' => COURSE_TYPE_SEMINAR, 'order' => 4, 'color' => '#7C3AED'],
            ['name' => ['en' => 'Practical', 'am' => 'ተግባራዊ'], 'code' => COURSE_TYPE_PRACTICAL, 'order' => 5, 'color' => '#F59E0B'],
        ],
    ],

    [
        'name' => ['en' => 'Course Offering Status', 'am' => 'የኮርስ አቅርቦት ሁኔታ'],
        'code' => COURSE_OFFERING_STATUS,
        'description' => ['en' => 'Position of an offering in the four-tier approval chain', 'am' => 'አቅርቦቱ በአራቱ የማጽደቅ ደረጃዎች ውስጥ ያለበት ቦታ'],
        'applies_to_model' => [MODEL_COURSE_OFFERING],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Draft', 'am' => 'ረቂቅ'], 'code' => COURSE_OFFERING_STATUS_DRAFT, 'order' => 1, 'color' => '#6B7280', 'icon' => 'pencil', 'is_default' => true],
            ['name' => ['en' => 'Submitted', 'am' => 'ቀርቧል'], 'code' => COURSE_OFFERING_STATUS_SUBMITTED, 'order' => 2, 'color' => '#0EA5E9', 'icon' => 'paper-airplane'],
            ['name' => ['en' => 'Committee Approved', 'am' => 'በኮሚቴ ጸድቋል'], 'code' => COURSE_OFFERING_STATUS_COMMITTEE_APPROVED, 'order' => 3, 'color' => '#38BDF8', 'icon' => 'check'],
            ['name' => ['en' => 'Department Approved', 'am' => 'በዲፓርትመንት ጸድቋል'], 'code' => COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED, 'order' => 4, 'color' => '#22D3EE', 'icon' => 'check'],
            ['name' => ['en' => 'College Approved', 'am' => 'በኮሌጅ ጸድቋል'], 'code' => COURSE_OFFERING_STATUS_COLLEGE_APPROVED, 'order' => 5, 'color' => '#34D399', 'icon' => 'check'],
            ['name' => ['en' => 'Registrar Approved', 'am' => 'በሬጅስትራር ጸድቋል'], 'code' => COURSE_OFFERING_STATUS_REGISTRAR_APPROVED, 'order' => 6, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => ['en' => 'Rejected', 'am' => 'ተቀባይነት አላገኘም'], 'code' => COURSE_OFFERING_STATUS_REJECTED, 'order' => 7, 'color' => '#EF4444', 'icon' => 'x-circle'],
        ],
        'transitions' => [
            ['from' => COURSE_OFFERING_STATUS_DRAFT, 'to' => COURSE_OFFERING_STATUS_SUBMITTED],
            ['from' => COURSE_OFFERING_STATUS_SUBMITTED, 'to' => COURSE_OFFERING_STATUS_COMMITTEE_APPROVED],
            ['from' => COURSE_OFFERING_STATUS_COMMITTEE_APPROVED, 'to' => COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED],
            ['from' => COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED, 'to' => COURSE_OFFERING_STATUS_COLLEGE_APPROVED],
            ['from' => COURSE_OFFERING_STATUS_COLLEGE_APPROVED, 'to' => COURSE_OFFERING_STATUS_REGISTRAR_APPROVED],

            // Any tier may reject, and a rejection can be reworked into a new draft.
            ['from' => COURSE_OFFERING_STATUS_SUBMITTED, 'to' => COURSE_OFFERING_STATUS_REJECTED],
            ['from' => COURSE_OFFERING_STATUS_COMMITTEE_APPROVED, 'to' => COURSE_OFFERING_STATUS_REJECTED],
            ['from' => COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED, 'to' => COURSE_OFFERING_STATUS_REJECTED],
            ['from' => COURSE_OFFERING_STATUS_COLLEGE_APPROVED, 'to' => COURSE_OFFERING_STATUS_REJECTED],
            ['from' => COURSE_OFFERING_STATUS_REJECTED, 'to' => COURSE_OFFERING_STATUS_DRAFT],
        ],
    ],

    [
        'name' => ['en' => 'Approval Level', 'am' => 'የማጽደቅ ደረጃ'],
        'code' => APPROVAL_LEVEL,
        'description' => ['en' => 'Which tier recorded an approval-trail entry', 'am' => 'የማጽደቅ መዝገቡን የመዘገበው ደረጃ'],
        'applies_to_model' => [MODEL_COURSE_OFFERING_APPROVAL],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Committee', 'am' => 'ኮሚቴ'], 'code' => APPROVAL_LEVEL_COMMITTEE, 'order' => 1, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Department', 'am' => 'ዲፓርትመንት'], 'code' => APPROVAL_LEVEL_DEPARTMENT, 'order' => 2, 'color' => '#14B8A6'],
            ['name' => ['en' => 'College', 'am' => 'ኮሌጅ'], 'code' => APPROVAL_LEVEL_COLLEGE, 'order' => 3, 'color' => '#7C3AED'],
            ['name' => ['en' => 'Registrar', 'am' => 'ሬጅስትራር'], 'code' => APPROVAL_LEVEL_REGISTRAR, 'order' => 4, 'color' => '#2563EB'],
        ],
    ],

    [
        'name' => ['en' => 'Approval Decision', 'am' => 'የማጽደቅ ውሳኔ'],
        'code' => APPROVAL_DECISION,
        'description' => ['en' => 'What a tier decided', 'am' => 'ደረጃው የወሰነው ውሳኔ'],
        'applies_to_model' => [MODEL_COURSE_OFFERING_APPROVAL],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Approved', 'am' => 'ጸድቋል'], 'code' => APPROVAL_DECISION_APPROVED, 'order' => 1, 'color' => '#10B981', 'icon' => 'check-circle', 'is_default' => true],
            ['name' => ['en' => 'Rejected', 'am' => 'ተቀባይነት አላገኘም'], 'code' => APPROVAL_DECISION_REJECTED, 'order' => 2, 'color' => '#EF4444', 'icon' => 'x-circle'],
            ['name' => ['en' => 'Revision Requested', 'am' => 'ማሻሻያ ተጠይቋል'], 'code' => APPROVAL_DECISION_REVISION_REQUESTED, 'order' => 3, 'color' => '#F59E0B', 'icon' => 'pencil'],
        ],
    ],

    [
        'name' => ['en' => 'Session Type', 'am' => 'የክፍለ ጊዜ ዓይነት'],
        'code' => SESSION_TYPE,
        'description' => ['en' => 'What kind of weekly meeting a class schedule row is', 'am' => 'የሳምንታዊ ስብሰባው ዓይነት'],
        'applies_to_model' => [MODEL_CLASS_SCHEDULE],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Lecture', 'am' => 'ንባብ'], 'code' => SESSION_TYPE_LECTURE, 'order' => 1, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Laboratory', 'am' => 'ላብራቶሪ'], 'code' => SESSION_TYPE_LAB, 'order' => 2, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Tutorial', 'am' => 'ማጠናከሪያ'], 'code' => SESSION_TYPE_TUTORIAL, 'order' => 3, 'color' => '#14B8A6'],
            ['name' => ['en' => 'Seminar', 'am' => 'ሴሚናር'], 'code' => SESSION_TYPE_SEMINAR, 'order' => 4, 'color' => '#7C3AED'],
            ['name' => ['en' => 'Practical', 'am' => 'ተግባራዊ'], 'code' => SESSION_TYPE_PRACTICAL, 'order' => 5, 'color' => '#F59E0B'],
        ],
    ],

    [
        'name' => ['en' => 'Class Schedule Status', 'am' => 'የክፍል ፕሮግራም ሁኔታ'],
        'code' => CLASS_SCHEDULE_STATUS,
        'description' => ['en' => 'Workflow label of a generated class meeting', 'am' => 'የተፈጠረው የክፍል ስብሰባ የሥራ ፍሰት ሁኔታ'],
        'applies_to_model' => [MODEL_CLASS_SCHEDULE],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Draft', 'am' => 'ረቂቅ'], 'code' => CLASS_SCHEDULE_STATUS_DRAFT, 'order' => 1, 'color' => '#6B7280', 'icon' => 'pencil', 'is_default' => true],
            ['name' => ['en' => 'Published', 'am' => 'ታትሟል'], 'code' => CLASS_SCHEDULE_STATUS_PUBLISHED, 'order' => 2, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => ['en' => 'Cancelled', 'am' => 'ተሰርዟል'], 'code' => CLASS_SCHEDULE_STATUS_CANCELLED, 'order' => 3, 'color' => '#EF4444', 'icon' => 'x-circle'],
        ],
        'transitions' => [
            ['from' => CLASS_SCHEDULE_STATUS_DRAFT, 'to' => CLASS_SCHEDULE_STATUS_PUBLISHED],
            ['from' => CLASS_SCHEDULE_STATUS_PUBLISHED, 'to' => CLASS_SCHEDULE_STATUS_CANCELLED],
        ],
    ],

    [
        'name' => ['en' => 'Exam Type', 'am' => 'የፈተና ዓይነት'],
        'code' => EXAM_TYPE,
        'description' => ['en' => 'Which sitting of a course an exam is', 'am' => 'የኮርሱ የትኛው ፈተና እንደሆነ'],
        'applies_to_model' => [MODEL_EXAM_SCHEDULE],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Midterm', 'am' => 'የመንፈቅ ፈተና'], 'code' => EXAM_TYPE_MIDTERM, 'order' => 1, 'color' => '#0EA5E9'],
            ['name' => ['en' => 'Final', 'am' => 'የመጨረሻ ፈተና'], 'code' => EXAM_TYPE_FINAL, 'order' => 2, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Makeup', 'am' => 'ማካካሻ ፈተና'], 'code' => EXAM_TYPE_MAKEUP, 'order' => 3, 'color' => '#F59E0B'],
            ['name' => ['en' => 'Quiz', 'am' => 'ኩዊዝ'], 'code' => EXAM_TYPE_QUIZ, 'order' => 4, 'color' => '#14B8A6'],
        ],
    ],

    [
        'name' => ['en' => 'Exam Schedule Status', 'am' => 'የፈተና ፕሮግራም ሁኔታ'],
        'code' => EXAM_SCHEDULE_STATUS,
        'description' => ['en' => 'Workflow label of an exam sitting', 'am' => 'የፈተናው የሥራ ፍሰት ሁኔታ'],
        'applies_to_model' => [MODEL_EXAM_SCHEDULE],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Draft', 'am' => 'ረቂቅ'], 'code' => EXAM_SCHEDULE_STATUS_DRAFT, 'order' => 1, 'color' => '#6B7280', 'icon' => 'pencil', 'is_default' => true],
            ['name' => ['en' => 'Pending Confirmation', 'am' => 'ማረጋገጫ በመጠባበቅ ላይ'], 'code' => EXAM_SCHEDULE_STATUS_PENDING_CONFIRMATION, 'order' => 2, 'color' => '#F59E0B', 'icon' => 'clock'],
            ['name' => ['en' => 'Confirmed', 'am' => 'ተረጋግጧል'], 'code' => EXAM_SCHEDULE_STATUS_CONFIRMED, 'order' => 3, 'color' => '#38BDF8', 'icon' => 'check'],
            ['name' => ['en' => 'Published', 'am' => 'ታትሟል'], 'code' => EXAM_SCHEDULE_STATUS_PUBLISHED, 'order' => 4, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => ['en' => 'Rejected', 'am' => 'ተቀባይነት አላገኘም'], 'code' => EXAM_SCHEDULE_STATUS_REJECTED, 'order' => 5, 'color' => '#F97316', 'icon' => 'x-circle'],
            ['name' => ['en' => 'Cancelled', 'am' => 'ተሰርዟል'], 'code' => EXAM_SCHEDULE_STATUS_CANCELLED, 'order' => 6, 'color' => '#EF4444', 'icon' => 'x-circle'],
        ],
        'transitions' => [
            // Two paths: straight to publication, or via department confirmation.
            ['from' => EXAM_SCHEDULE_STATUS_DRAFT, 'to' => EXAM_SCHEDULE_STATUS_PUBLISHED],
            ['from' => EXAM_SCHEDULE_STATUS_DRAFT, 'to' => EXAM_SCHEDULE_STATUS_PENDING_CONFIRMATION],
            ['from' => EXAM_SCHEDULE_STATUS_PENDING_CONFIRMATION, 'to' => EXAM_SCHEDULE_STATUS_CONFIRMED],
            ['from' => EXAM_SCHEDULE_STATUS_CONFIRMED, 'to' => EXAM_SCHEDULE_STATUS_PUBLISHED],
            ['from' => EXAM_SCHEDULE_STATUS_PUBLISHED, 'to' => EXAM_SCHEDULE_STATUS_CANCELLED],
            ['from' => EXAM_SCHEDULE_STATUS_DRAFT, 'to' => EXAM_SCHEDULE_STATUS_CANCELLED],
        ],
    ],

    [
        'name' => ['en' => 'Generation Type', 'am' => 'የማመንጨት ዓይነት'],
        'code' => GENERATION_TYPE,
        'description' => ['en' => 'Which scheduler produced a generation run', 'am' => 'ሩጫውን ያመነጨው ፕሮግራም አውጪ'],
        'applies_to_model' => [MODEL_SCHEDULE_GENERATION_RUN],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Class', 'am' => 'ክፍል'], 'code' => GENERATION_TYPE_CLASS, 'order' => 1, 'color' => '#2563EB', 'is_default' => true],
            ['name' => ['en' => 'Exam', 'am' => 'ፈተና'], 'code' => GENERATION_TYPE_EXAM, 'order' => 2, 'color' => '#7C3AED'],
        ],
    ],

    [
        'name' => ['en' => 'Generation Status', 'am' => 'የማመንጨት ሁኔታ'],
        'code' => GENERATION_STATUS,
        'description' => ['en' => 'Outcome of one automatic-scheduling execution', 'am' => 'የአንድ አውቶማቲክ ፕሮግራም ማውጣት ውጤት'],
        'applies_to_model' => [MODEL_SCHEDULE_GENERATION_RUN],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Running', 'am' => 'በሂደት ላይ'], 'code' => GENERATION_STATUS_RUNNING, 'order' => 1, 'color' => '#F59E0B', 'icon' => 'clock', 'is_default' => true],
            ['name' => ['en' => 'Completed', 'am' => 'ተጠናቋል'], 'code' => GENERATION_STATUS_COMPLETED, 'order' => 2, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => ['en' => 'Failed', 'am' => 'አልተሳካም'], 'code' => GENERATION_STATUS_FAILED, 'order' => 3, 'color' => '#EF4444', 'icon' => 'x-circle'],
        ],
    ],

    [
        'name' => ['en' => 'Invigilation Request Status', 'am' => 'የፈታኝ ጥያቄ ሁኔታ'],
        'code' => INVIGILATION_REQUEST_STATUS,
        'description' => [
            'en' => 'Where a registrar request for invigilators has got to',
            'am' => 'የመዝጋቢው የፈታኝ ጥያቄ የደረሰበት ደረጃ',
        ],
        'applies_to_model' => [MODEL_INVIGILATION_REQUEST],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Draft', 'am' => 'ረቂቅ'], 'code' => INVIGILATION_REQUEST_STATUS_DRAFT, 'order' => 1, 'color' => '#6B7280', 'is_default' => true],
            ['name' => ['en' => 'Sent', 'am' => 'ተልኳል'], 'code' => INVIGILATION_REQUEST_STATUS_SENT, 'order' => 2, 'color' => '#2563EB'],
            ['name' => ['en' => 'Closed', 'am' => 'ተዘግቷል'], 'code' => INVIGILATION_REQUEST_STATUS_CLOSED, 'order' => 3, 'color' => '#059669'],
        ],
        // A request is sent once and then closed; there is no way back, because
        // a department that has already submitted people cannot un-see the ask.
        'transitions' => [
            ['from' => INVIGILATION_REQUEST_STATUS_DRAFT, 'to' => INVIGILATION_REQUEST_STATUS_SENT],
            ['from' => INVIGILATION_REQUEST_STATUS_SENT, 'to' => INVIGILATION_REQUEST_STATUS_CLOSED],
        ],
    ],

    [
        'name' => ['en' => 'Invigilator Role', 'am' => 'የተቆጣጣሪ ሚና'],
        'code' => INVIGILATOR_ROLE,
        'description' => ['en' => 'Chief or assisting invigilator at an exam', 'am' => 'በፈተናው ላይ ዋና ወይም ረዳት ተቆጣጣሪ'],
        'applies_to_model' => [MODEL_EXAM_INVIGILATOR_ASSIGNMENT],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Chief', 'am' => 'ዋና'], 'code' => INVIGILATOR_ROLE_CHIEF, 'order' => 1, 'color' => '#2563EB'],
            ['name' => ['en' => 'Assistant', 'am' => 'ረዳት'], 'code' => INVIGILATOR_ROLE_ASSISTANT, 'order' => 2, 'color' => '#0EA5E9', 'is_default' => true],
        ],
    ],

    [
        'name' => ['en' => 'Invigilation Status', 'am' => 'የቁጥጥር ሁኔታ'],
        'code' => INVIGILATION_STATUS,
        'description' => ['en' => 'Where an invigilation duty stands', 'am' => 'የቁጥጥር ተግባሩ ያለበት ደረጃ'],
        'applies_to_model' => [MODEL_EXAM_INVIGILATOR_ASSIGNMENT],
        'is_system' => true,
        'values' => [
            ['name' => ['en' => 'Assigned', 'am' => 'ተመድቧል'], 'code' => INVIGILATION_STATUS_ASSIGNED, 'order' => 1, 'color' => '#0EA5E9', 'icon' => 'clock', 'is_default' => true],
            ['name' => ['en' => 'Accepted', 'am' => 'ተቀብሏል'], 'code' => INVIGILATION_STATUS_ACCEPTED, 'order' => 2, 'color' => '#10B981', 'icon' => 'check-circle'],
            ['name' => ['en' => 'Declined', 'am' => 'አልተቀበለም'], 'code' => INVIGILATION_STATUS_DECLINED, 'order' => 3, 'color' => '#EF4444', 'icon' => 'x-circle'],
            ['name' => ['en' => 'Replaced', 'am' => 'ተተክቷል'], 'code' => INVIGILATION_STATUS_REPLACED, 'order' => 4, 'color' => '#6B7280', 'icon' => 'refresh'],
        ],
    ],
];
