<?php

// User Management Models
define('MODEL_USER', 'user');
define('MODEL_USER_DETAIL', 'user_detail');
define('MODEL_USER_LOG', 'user_log');
define('MODEL_LOGIN_ATTEMPT', 'login_attempt');
define('MODEL_USER2_F_A', 'user2_f_a');
define('MODEL_USER_BACKUP_CODE', 'user_backup_code');
define('MODEL_USER_O_T_P_CODE', 'user_o_t_p_code');
define('MODEL_OTP', 'otp');

// Role & Permission Models
define('MODEL_ROLE', 'role');
define('MODEL_PERMISSION', 'permission');
define('MODEL_PERMISSION_GROUP', 'permission_group');
define('MODEL_ROLE_PERMISSION', 'role_permission');
define('MODEL_USER_ROLE_BINDING', 'user_role_binding');
define('MODEL_USER_PERMISSION_OVERRIDE', 'user_permission_override');

// Lookup Models
define('MODEL_LOOKUP_TYPE', 'lookup_type');
define('MODEL_LOOKUP_VALUE', 'lookup_value');
define('MODEL_LOOKUP_TRANSITION', 'lookup_transition');

// Scheduling Models — the `applies_to_model` targets of the scheduling lookup types
define('MODEL_PROGRAM', 'programs');
define('MODEL_SEMESTER', 'semesters');
define('MODEL_ROOM', 'rooms');
define('MODEL_COURSE', 'courses');
define('MODEL_INSTRUCTOR', 'instructors');
define('MODEL_COURSE_OFFERING', 'course_offerings');
define('MODEL_COURSE_OFFERING_APPROVAL', 'course_offering_approvals');
define('MODEL_CLASS_SCHEDULE', 'class_schedules');
define('MODEL_EXAM_SCHEDULE', 'exam_schedules');
define('MODEL_SCHEDULE_GENERATION_RUN', 'schedule_generation_runs');
define('MODEL_EXAM_INVIGILATOR_ASSIGNMENT', 'exam_invigilator_assignments');
