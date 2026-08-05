<?php

// user status types
define('USER_STATUS', 'USER_STATUS');

// User Status Values
define('USER_STATUS_ACTIVE', 'ACTIVE');
define('USER_STATUS_INACTIVE', 'INACTIVE');
define('USER_STATUS_PENDING_VERIFICATION', 'PENDING_VERIFICATION');
define('USER_STATUS_SUSPENDED', 'SUSPENDED');
define('USER_STATUS_LOCKED', 'LOCKED');

// Generic Status
define('GENERIC_STATUS', 'GENERIC_STATUS');

define('LOOKUP_VALUE_STATUS', 'LOOKUP_VALUE_STATUS');

define('LOOKUP_VALUE_STATUS_PENDING', 'PENDING');
define('LOOKUP_VALUE_STATUS_ACCEPT_FOR_ALL', 'ACCEPT_FOR_ALL');
define('LOOKUP_VALUE_STATUS_ACCEPT_FOR_THIS', 'ACCEPT_FOR_THIS');
define('LOOKUP_VALUE_STATUS_REJECT', 'REJECT');

/**
 * Scheduling lookup vocabularies — Final Schema.md § "Lookup vocabularies".
 * Every `*_lookup_value_id` column points at values of the matching type.
 * Values are resolved by these stable codes, never by an auto-increment id.
 */
define('DEGREE_LEVEL', 'DEGREE_LEVEL');
define('DEGREE_LEVEL_CERTIFICATE', 'certificate');
define('DEGREE_LEVEL_DIPLOMA', 'diploma');
define('DEGREE_LEVEL_BACHELOR', 'bachelor');
define('DEGREE_LEVEL_MASTER', 'master');
define('DEGREE_LEVEL_PHD', 'phd');

define('SEMESTER_STATUS', 'SEMESTER_STATUS');
define('SEMESTER_STATUS_PLANNING', 'planning');
define('SEMESTER_STATUS_SCHEDULING', 'scheduling');
define('SEMESTER_STATUS_ACTIVE', 'active');
define('SEMESTER_STATUS_CLOSED', 'closed');

define('ROOM_TYPE', 'ROOM_TYPE');
define('ROOM_TYPE_LECTURE_HALL', 'lecture_hall');
define('ROOM_TYPE_LAB', 'lab');
define('ROOM_TYPE_SEMINAR_ROOM', 'seminar_room');
define('ROOM_TYPE_WORKSHOP', 'workshop');
define('ROOM_TYPE_AUDITORIUM', 'auditorium');
define('ROOM_TYPE_EXAM_HALL', 'exam_hall');

define('COURSE_TYPE', 'COURSE_TYPE');
define('COURSE_TYPE_LECTURE', 'lecture');
define('COURSE_TYPE_LAB', 'lab');
define('COURSE_TYPE_LECTURE_LAB', 'lecture_lab');
define('COURSE_TYPE_SEMINAR', 'seminar');
define('COURSE_TYPE_PRACTICAL', 'practical');

define('COURSE_OFFERING_STATUS', 'COURSE_OFFERING_STATUS');
define('COURSE_OFFERING_STATUS_DRAFT', 'draft');
define('COURSE_OFFERING_STATUS_SUBMITTED', 'submitted');
define('COURSE_OFFERING_STATUS_COMMITTEE_APPROVED', 'committee_approved');
define('COURSE_OFFERING_STATUS_DEPARTMENT_APPROVED', 'department_approved');
define('COURSE_OFFERING_STATUS_COLLEGE_APPROVED', 'college_approved');
define('COURSE_OFFERING_STATUS_REGISTRAR_APPROVED', 'registrar_approved');
define('COURSE_OFFERING_STATUS_REJECTED', 'rejected');

define('APPROVAL_LEVEL', 'APPROVAL_LEVEL');
define('APPROVAL_LEVEL_COMMITTEE', 'committee');
define('APPROVAL_LEVEL_DEPARTMENT', 'department');
define('APPROVAL_LEVEL_COLLEGE', 'college');
define('APPROVAL_LEVEL_REGISTRAR', 'registrar');

define('APPROVAL_DECISION', 'APPROVAL_DECISION');
define('APPROVAL_DECISION_APPROVED', 'approved');
define('APPROVAL_DECISION_REJECTED', 'rejected');
define('APPROVAL_DECISION_REVISION_REQUESTED', 'revision_requested');

define('SESSION_TYPE', 'SESSION_TYPE');
define('SESSION_TYPE_LECTURE', 'lecture');
define('SESSION_TYPE_LAB', 'lab');
define('SESSION_TYPE_TUTORIAL', 'tutorial');
define('SESSION_TYPE_SEMINAR', 'seminar');
define('SESSION_TYPE_PRACTICAL', 'practical');

define('CLASS_SCHEDULE_STATUS', 'CLASS_SCHEDULE_STATUS');
define('CLASS_SCHEDULE_STATUS_DRAFT', 'draft');
define('CLASS_SCHEDULE_STATUS_PUBLISHED', 'published');
define('CLASS_SCHEDULE_STATUS_CANCELLED', 'cancelled');

define('EXAM_TYPE', 'EXAM_TYPE');
define('EXAM_TYPE_MIDTERM', 'midterm');
define('EXAM_TYPE_FINAL', 'final');
define('EXAM_TYPE_MAKEUP', 'makeup');
define('EXAM_TYPE_QUIZ', 'quiz');

define('EXAM_SCHEDULE_STATUS', 'EXAM_SCHEDULE_STATUS');
define('EXAM_SCHEDULE_STATUS_DRAFT', 'draft');
define('EXAM_SCHEDULE_STATUS_PENDING_CONFIRMATION', 'pending_confirmation');
define('EXAM_SCHEDULE_STATUS_CONFIRMED', 'confirmed');
define('EXAM_SCHEDULE_STATUS_PUBLISHED', 'published');
define('EXAM_SCHEDULE_STATUS_REJECTED', 'rejected');
define('EXAM_SCHEDULE_STATUS_CANCELLED', 'cancelled');

define('GENERATION_TYPE', 'GENERATION_TYPE');
define('GENERATION_TYPE_CLASS', 'class');
define('GENERATION_TYPE_EXAM', 'exam');

define('GENERATION_STATUS', 'GENERATION_STATUS');
define('GENERATION_STATUS_RUNNING', 'running');
define('GENERATION_STATUS_COMPLETED', 'completed');
define('GENERATION_STATUS_FAILED', 'failed');

define('INVIGILATOR_ROLE', 'INVIGILATOR_ROLE');
define('INVIGILATOR_ROLE_CHIEF', 'chief');
define('INVIGILATOR_ROLE_ASSISTANT', 'assistant');

define('INVIGILATION_STATUS', 'INVIGILATION_STATUS');
define('INVIGILATION_STATUS_ASSIGNED', 'assigned');
define('INVIGILATION_STATUS_ACCEPTED', 'accepted');
define('INVIGILATION_STATUS_DECLINED', 'declined');
define('INVIGILATION_STATUS_REPLACED', 'replaced');
