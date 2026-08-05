# Course and Exam Scheduling System — Database Schema

> Standalone schema reference. Each table is documented on its own terms — purpose, columns, keys,
> relationships and design notes.
>
> **Stack:** PostgreSQL 13+, Laravel 13, Vue. Builds on the existing **User Management** module
> (`users`, `roles`, `permissions`, and the **lookup engine** `lookup_types` / `lookup_values` /
> `lookup_transitions`), which is referenced and never redefined here.
>
> **18 tables**, grouped: academic hierarchy · physical resources · catalogue & people ·
> offering & approval · scheduling outputs · invigilation.

---

## Conventions

Applied to every table unless its section says otherwise — stated once here so each table stays
readable.

| Convention | Rule |
|---|---|
| Primary key | `id` — `bigIncrements` |
| Public identifier | `uuid` — `string(50)`, unique, on every user-facing entity (the frontend routes by `uuid`, never by `id`) |
| Names | `name` / `title` — `jsonb` translatable `{"en": "...", "am": "..."}` |
| System code | `code` — `string`, unique |
| **Statuses & types** | **`<name>_lookup_value_id` → `lookup_values`** (the existing lookup engine). Never a raw string enum |
| Record state | `state` — `unsignedSmallInteger` (`STATE_ACTIVE` = 1 / `STATE_INACTIVE` = 0), where a table needs an active/void flag |
| Master-data lifecycle | `is_active` — `boolean` default `true`, plus `deleted_at` soft delete |
| Creator (master data) | `user_id` → `users` (`restrict`) |
| Actors (workflow data) | explicit columns — `created_by_id`, `submitted_by_id`, `reviewed_by_id`, … |
| Foreign-key delete rule | `restrict` by default (including every `*_lookup_value_id`); `cascade` only from the two child tables meaningless without their parent |
| Timestamps | `created_at`, `updated_at` |

**Lookup engine.** Every enumerated column (status, type, level, decision, role, degree level, room
type, …) is a foreign key to `lookup_values`, exactly as the existing schema does it — one join
resolves the human label, values are added without a migration, and the existing
`lookup_transitions` table can guard lifecycle moves. The lookup *types* this schema registers are
listed in the next section. Value-specific business rules that a foreign key cannot express (e.g.
"a rejection must carry a remark") are enforced in the Laravel Form Request / service layer, per the
project's established pattern.

**Migration note.** `EXCLUDE` constraints, composite foreign keys and the few numeric `CHECK`
constraints are emitted with `DB::statement(...)` (raw DDL) inside the relevant migration — Laravel's
fluent builder cannot express them. A one-time setup migration runs
`CREATE EXTENSION IF NOT EXISTS btree_gist;` and declares a `timerange` range type over `time`.

---

## Lookup vocabularies (registered lookup types)

Each becomes one `lookup_types` row (`is_system = true`) with its `lookup_values` seeded by stable
`code`. Every `*_lookup_value_id` column below points at values of the matching type.

| `lookup_types.code` | `applies_to_model` | Seeded values (by `code`) |
|---|---|---|
| `DEGREE_LEVEL` | `["programs"]` | certificate, diploma, bachelor, master, phd |
| `SEMESTER_STATUS` | `["semesters"]` | planning, scheduling, active, closed |
| `ROOM_TYPE` | `["rooms"]` | lecture_hall, lab, seminar_room, workshop, auditorium, exam_hall |
| `COURSE_TYPE` | `["courses"]` | lecture, lab, lecture_lab, seminar, practical |
| `COURSE_OFFERING_STATUS` | `["course_offerings"]` | draft, submitted, committee_approved, department_approved, college_approved, registrar_approved, rejected |
| `APPROVAL_LEVEL` | `["course_offering_approvals"]` | committee, department, college, registrar |
| `APPROVAL_DECISION` | `["course_offering_approvals"]` | approved, rejected, revision_requested |
| `SESSION_TYPE` | `["class_schedules"]` | lecture, lab, tutorial, seminar, practical |
| `CLASS_SCHEDULE_STATUS` | `["class_schedules"]` | draft, published, cancelled |
| `EXAM_TYPE` | `["exam_schedules"]` | midterm, final, makeup, quiz |
| `EXAM_SCHEDULE_STATUS` | `["exam_schedules"]` | draft, pending_confirmation, confirmed, published, rejected, cancelled |
| `GENERATION_TYPE` | `["schedule_generation_runs"]` | class, exam |
| `GENERATION_STATUS` | `["schedule_generation_runs"]` | running, completed, failed |
| `INVIGILATOR_ROLE` | `["exam_invigilator_assignments"]` | chief, assistant |
| `INVIGILATION_STATUS` | `["exam_invigilator_assignments"]` | assigned, accepted, declined, replaced |

**Lifecycle guarding.** `COURSE_OFFERING_STATUS`, `CLASS_SCHEDULE_STATUS`, `EXAM_SCHEDULE_STATUS`
and `SEMESTER_STATUS` are lifecycles — seed `lookup_transitions` rows for their legal moves
(e.g. `draft → published`, `pending_confirmation → confirmed`) so the existing engine enforces them,
exactly as it does for other statuses in the system.

---

## Table list

| # | Table | Group |
|---|---|---|
| 1 | `campuses` | Physical resources |
| 2 | `buildings` | Physical resources |
| 3 | `colleges` | Academic hierarchy |
| 4 | `departments` | Academic hierarchy |
| 5 | `programs` | Academic hierarchy |
| 6 | `academic_years` | Academic hierarchy |
| 7 | `semesters` | Academic hierarchy |
| 8 | `sections` | Academic hierarchy |
| 9 | `rooms` | Physical resources |
| 10 | `courses` | Catalogue |
| 11 | `instructors` | People |
| 12 | `course_offerings` | Offering & approval |
| 13 | `course_offering_approvals` | Offering & approval |
| 14 | `class_schedules` | Scheduling outputs |
| 15 | `exam_schedules` | Scheduling outputs |
| 16 | `schedule_generation_runs` | Scheduling outputs |
| 17 | `invigilator_availabilities` | Invigilation |
| 18 | `exam_invigilator_assignments` | Invigilation |

---
---

# PHYSICAL RESOURCES

## 1. campuses

**Purpose:** A geographic site of the institution. The root of the physical location hierarchy —
buildings belong to a campus, rooms belong to a building.
**Business justification:** A room cannot be identified by code alone across a multi-site
institution ("Room 101" exists on every campus). Campus + building make a room's location explicit
and reusable by anything that needs to know *where* a resource is.

### Columns

    - id            -- PK
    - uuid          -- string(50), unique
    - code          -- string(20), unique, e.g. MAIN
    - name          -- jsonb, {"en": "Main Campus"}
    - address       -- jsonb, nullable
    - city          -- string(100), nullable
    - is_main       -- boolean, default false (the principal campus)
    - is_active     -- boolean, default true
    - user_id       -- FK -> users, creator
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Partial unique | `is_main` WHERE `is_main = true AND deleted_at IS NULL` — one principal campus |
| Foreign key | `user_id` → `users.id`, `restrict` |
| Index | `is_active` |

### Relationships

`hasMany(Building)`; `hasManyThrough(Room, Building)`.

### Design notes

Deliberately identity-only — code, name, address. No coordinates or proximity data, because
scheduling here does not optimise for walking distance. A single-site institution has one row with
`is_main = true` and never revisits this table.

---

## 2. buildings

**Purpose:** A physical building on a campus, containing rooms.
**Business justification:** Rooms are grouped and located by building; timetables and exam venue
lists read "NB-301, New Block, Main Campus". Building is the middle tier that makes a room's
location complete.

### Columns

    - id            -- PK
    - uuid          -- string(50), unique
    - code          -- string(20), unique, e.g. NB
    - name          -- jsonb, {"en": "New Block"}
    - campus_id     -- FK -> campuses
    - floors        -- smallInteger, nullable, number of floors
    - is_active     -- boolean, default true
    - user_id       -- FK -> users, creator
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Foreign key | `campus_id` → `campuses.id`, `restrict`; `user_id` → `users.id`, `restrict` |
| Composite index | `(campus_id, is_active)` |

### Relationships

`belongsTo(Campus)`; `hasMany(Room)`.

### Design notes

`code` is globally unique so a building can be named on a printout without repeating its campus.
`floors` is informational (used to render a building's room list); no floor-level entity is
modelled.

---
---

# ACADEMIC HIERARCHY

## 3. colleges

**Purpose:** Top of the academic hierarchy; the College-Dean approval tier.
**Business justification:** The institution is organised into colleges; the Dean is a mandatory
approval step for every course offering.

### Columns

    - id            -- PK
    - uuid          -- string(50), unique
    - code          -- string(20), unique, e.g. COET
    - name          -- jsonb
    - dean_user_id  -- FK -> users, nullable (routes the college-approval step)
    - is_active     -- boolean, default true
    - user_id       -- FK -> users, creator
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Foreign key | `dean_user_id` → `users.id`, `nullOnDelete`; `user_id` → `users.id`, `restrict` |
| Index | `is_active` |

### Relationships

`hasMany(Department)`; `belongsTo(User, 'dean_user_id')` as `dean`.

### Design notes

`dean_user_id` is a routing pointer — it names who the college-approval step goes to. It is **not**
the authorization source; whether a user may act as Dean is an RBAC question answered by the
existing `roles` / `permissions` tables. Nullable because a college can sit vacant between deans.

---

## 4. departments

**Purpose:** Owns programs, courses, instructors and course offerings. The Committee and
Department-Head approval tiers act here.
**Business justification:** The department is the ownership root for every scheduling input and the
first two approval tiers of the offering workflow.

### Columns

    - id            -- PK
    - uuid          -- string(50), unique
    - code          -- string(20), unique, e.g. CS
    - name          -- jsonb
    - college_id    -- FK -> colleges
    - head_user_id  -- FK -> users, nullable (routes the department-approval step)
    - is_active     -- boolean, default true
    - user_id       -- FK -> users, creator
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Foreign key | `college_id` → `colleges.id`, `restrict`; `head_user_id` → `users.id`, `nullOnDelete` |
| Composite index | `(college_id, is_active)` |

### Relationships

`belongsTo(College)`; `hasMany(Program, Course, Instructor, CourseOffering)`;
`belongsTo(User, 'head_user_id')` as `head`.

---

## 5. programs

**Purpose:** A degree program. Gives a `section` its cohort identity ("BSc CS Year 2 Section A").
**Business justification:** A department running BSc and MSc has two distinct "Year 1" cohorts;
the program distinguishes them.

### Columns

    - id                          -- PK
    - uuid                        -- string(50), unique
    - code                        -- string(30), unique, e.g. BSC-CS
    - name                        -- jsonb
    - department_id               -- FK -> departments
    - degree_level_lookup_value_id -- FK -> lookup_values (DEGREE_LEVEL)
    - duration_years              -- smallInteger, CHECK 1..10
    - is_active                   -- boolean, default true
    - user_id                     -- FK -> users, creator
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Foreign key | `department_id` → `departments.id`, `restrict` |
| Foreign key | `degree_level_lookup_value_id` → `lookup_values.id`, `restrict` |
| Composite index | `(department_id, is_active)` |

### Relationships

`belongsTo(Department)`; `belongsTo(LookupValue, 'degree_level_lookup_value_id')` as `degreeLevel`;
`hasMany(Section)`.

### Design notes

If every department runs exactly one program, this table can be dropped and `department_id` moved
onto `sections` with no other change.

---

## 6. academic_years

**Purpose:** Parent period; groups semesters and scopes sections.
**Business justification:** Semesters and cohorts belong to an academic year; year-over-year
reporting groups by it.

### Columns

    - id          -- PK
    - uuid        -- string(50), unique
    - code        -- string(20), unique, e.g. 2025/26
    - start_date  -- date
    - end_date    -- date
    - is_current  -- boolean, default false (exactly one)
    - user_id     -- FK -> users, creator
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Check | `end_date > start_date` |
| Partial unique | `is_current` WHERE `is_current = true` — exactly one current year |
| Foreign key | `user_id` → `users.id`, `restrict` |

### Relationships

`hasMany(Semester, Section)`.

---

## 7. semesters

**Purpose:** The scheduling unit. Every course offering and every schedule is scoped to one.
**Business justification:** Scheduling happens per semester; a semester's status gates when
scheduling may run.

### Columns

    - id                       -- PK
    - uuid                     -- string(50), unique
    - academic_year_id         -- FK -> academic_years
    - term                     -- smallInteger, 1/2/3, CHECK 1..3
    - name                     -- jsonb, nullable
    - start_date               -- date
    - end_date                 -- date
    - status_lookup_value_id   -- FK -> lookup_values (SEMESTER_STATUS)
    - is_current               -- boolean, default false
    - user_id                  -- FK -> users, creator
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid` |
| Composite unique | `(academic_year_id, term)` — one Semester 1 per year |
| Check | `end_date > start_date` |
| Partial unique | `is_current` WHERE `is_current = true` |
| Foreign key | `academic_year_id` → `academic_years.id`, `restrict`; `status_lookup_value_id` → `lookup_values.id`, `restrict` |
| Composite index | `(academic_year_id, status_lookup_value_id)` |

### Relationships

`belongsTo(AcademicYear)`; `belongsTo(LookupValue, 'status_lookup_value_id')` as `status`;
`hasMany(CourseOffering, ScheduleGenerationRun, InvigilatorAvailability)`.

### Design notes

The academic year is reachable through `semesters`, so no other table stores a second copy of it —
the year is always one join away via the semester. `SEMESTER_STATUS` (planning → scheduling →
active → closed) is a guarded lifecycle via `lookup_transitions`.

---

## 8. sections

**Purpose:** The student cohort — the unit of student-group conflict detection.
**Business justification:** A section cannot be in two classes or two exams at the same time; this
is the row those conflict rules are checked against.

### Columns

    - id                 -- PK
    - uuid               -- string(50), unique
    - program_id         -- FK -> programs
    - academic_year_id   -- FK -> academic_years
    - year_level         -- smallInteger, CHECK 1..10
    - label              -- string(10), e.g. A, B, Night-1
    - expected_students  -- integer, default 0
    - is_active          -- boolean, default true
    - user_id            -- FK -> users, creator
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid` |
| Composite unique | `(program_id, academic_year_id, year_level, label)` |
| Foreign key | `program_id` → `programs.id`, `restrict`; `academic_year_id` → `academic_years.id`, `restrict` |
| Composite index | `(academic_year_id, program_id, year_level)` |

### Relationships

`belongsTo(Program, AcademicYear)`; `hasMany(CourseOffering)`.

### Design notes

A section is scoped to the **academic year**, not the semester — "CS Year 2 Section A" is the same
cohort across both semesters of the year.

---
---

# PHYSICAL RESOURCES (continued)

## 9. rooms

**Purpose:** A bookable venue for classes and exams, located by building and campus.
**Business justification:** Room capacity, type and location drive scheduling; a room is uniquely
identified by its `code` and located through `building → campus`.

### Columns

    - id                      -- PK
    - uuid                    -- string(50), unique
    - code                    -- string(30), unique, e.g. NB-301
    - name                    -- jsonb, nullable
    - building_id             -- FK -> buildings
    - floor                   -- smallInteger, nullable (negative = basement)
    - room_type_lookup_value_id -- FK -> lookup_values (ROOM_TYPE)
    - capacity                -- integer, CHECK > 0 (teaching capacity)
    - exam_capacity           -- integer, nullable, CHECK null or > 0 (spaced exam seating)
    - is_exam_venue           -- boolean, default false
    - is_active               -- boolean, default true
    - user_id                 -- FK -> users, creator
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Foreign key | `building_id` → `buildings.id`, `restrict`; `room_type_lookup_value_id` → `lookup_values.id`, `restrict`; `user_id` → `users.id`, `restrict` |
| Index | `building_id`, `room_type_lookup_value_id` |
| Partial index | `(room_type_lookup_value_id, capacity)` WHERE `is_active` — room search |
| Partial index | `(is_exam_venue, exam_capacity)` WHERE `is_active` — exam venue search |

### Relationships

`belongsTo(Building)`; `belongsTo(Campus)` through `Building` (`hasOneThrough`);
`belongsTo(LookupValue, 'room_type_lookup_value_id')` as `roomType`;
`hasMany(ClassSchedule, ExamSchedule)`.

### Design notes

`capacity` and `exam_capacity` are separate: spaced exam seating uses roughly half a hall's teaching
capacity, so a single number would either overbook every exam or waste half of every classroom.
`is_exam_venue` is a use-flag distinct from `room_type` — a large `lecture_hall` may serve as an
exam venue, so eligibility is not derived from type. `floor` is signed so basements are honest.

---
---

# CATALOGUE

## 10. courses

**Purpose:** The reusable course catalogue. Stable across semesters and years.
**Business justification:** Courses are defined once and offered many times; the catalogue holds no
semester, instructor or enrolment, which is what makes it reusable.

### Columns

    - id                      -- PK
    - uuid                    -- string(50), unique
    - code                    -- string(30), unique, e.g. CS101
    - title                   -- jsonb
    - department_id           -- FK -> departments (ownership)
    - credit_hours            -- decimal(4,2), CHECK > 0
    - contact_hours           -- decimal(4,2), nullable, CHECK null or > 0
    - course_type_lookup_value_id -- FK -> lookup_values (COURSE_TYPE)
    - description             -- jsonb, nullable
    - is_active               -- boolean, default true
    - user_id                 -- FK -> users, creator
    - lecture_hours_per_week    -- decimal(4,2), nullable, weekly lecture load
    - lab_hours_per_week        -- decimal(4,2), nullable, weekly lab load
    - tutorial_hours_per_week   -- decimal(4,2), nullable, weekly tutorial load
    - sessions_per_week         -- smallInteger, nullable, meetings the generator fans out
    - created_at / updated_at / deleted_at


### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `code` |
| Foreign key | `department_id` → `departments.id`, `restrict`; `course_type_lookup_value_id` → `lookup_values.id`, `restrict` |
| Composite index | `(department_id, is_active)` |

### Relationships

`belongsTo(Department)`; `belongsTo(LookupValue, 'course_type_lookup_value_id')` as `courseType`;
`hasMany(CourseOffering)`.

### Design notes

`code` is globally unique because it prints bare on timetables and exam papers ("CS101"), where a
reader has no department context to disambiguate. Ownership is a single hop to the department.

---
---

# PEOPLE

## 11. instructors

**Purpose:** A person who teaches, invigilates, or both.
**Business justification:** Instructors and invigilators are one population; two capability flags
distinguish role without a second table.

### Columns

    - id               -- PK
    - uuid             -- string(50), unique
    - user_id          -- FK -> users, nullable, unique (login account if one exists — the PERSON, not a creator)
    - employee_no      -- string(50), unique
    - full_name        -- jsonb
    - email            -- string(150), nullable
    - phone            -- string(30), nullable
    - department_id    -- FK -> departments
    - academic_rank    -- string(40), nullable
    - can_teach        -- boolean, default true
    - can_invigilate   -- boolean, default true
    - max_weekly_hours -- decimal(5,2), nullable (soft workload ceiling, checked in service)
    - is_active        -- boolean, default true
    - created_at / updated_at / deleted_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid`, `user_id`, `employee_no` |
| Foreign key | `user_id` → `users.id`, `nullOnDelete`; `department_id` → `departments.id`, `restrict` |
| Partial index | `(department_id, can_teach)` WHERE `is_active` — assignable teachers |
| Partial index | `(department_id, can_invigilate)` WHERE `is_active` — invigilator pool |

### Relationships

`belongsTo(User, 'user_id')` (one-to-one, nullable), `belongsTo(Department)`;
`hasMany(CourseOffering, ClassSchedule, InvigilatorAvailability, ExamInvigilatorAssignment)`.

### Design notes

`user_id` here identifies **the person** the instructor is (nullable, because the registry precedes
the login account), not the record's creator. A lab technician who only invigilates is
`can_teach = false, can_invigilate = true`; a visiting lecturer exempt from duty is the reverse.
`academic_rank` stays a plain string (free-form HR label); it is a candidate for a lookup type if it
ever drives behaviour. `full_name` is a jsonb snapshot so rosters and print artifacts remain correct
even for instructors with no linked `users` row.

---
---

# OFFERING & APPROVAL

## 12. course_offerings

**Purpose:** The pivot of the whole system — a course planned for one semester, one section, one
instructor. It flows through the four-tier approval chain and feeds both scheduling modules.
**Business justification:** Everything downstream (approvals, class schedules, exam schedules)
references an offering; its status is the offering's position in the approval workflow.

### Columns

    - id                      -- PK
    - uuid                    -- string(50), unique
    - semester_id             -- FK -> semesters
    - course_id               -- FK -> courses
    - department_id           -- FK -> departments (the offering department)
    - program_id              -- FK -> programs, nullable
    - section_id              -- FK -> sections, nullable
    - instructor_id           -- FK -> instructors, nullable (proposed teacher)
    - expected_students       -- integer, default 0
    - status_lookup_value_id  -- FK -> lookup_values (COURSE_OFFERING_STATUS)
    - status_changed_at       -- timestamp, nullable
    - created_by_id           -- FK -> users
    - submitted_by_id         -- FK -> users, nullable
    - submitted_at            -- timestamp, nullable
    - remark                  -- text, nullable
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid` |
| Composite unique | `(semester_id, course_id, section_id)` |
| Partial unique | `(semester_id, course_id)` WHERE `section_id IS NULL` |
| Helper unique | `(id, semester_id)` and `(id, section_id)` — targets for the schedule tables' composite FKs |
| Foreign key | `semester_id`, `course_id`, `department_id` → respective, `restrict`; `program_id`, `section_id`, `instructor_id` → respective, `restrict`, nullable; `status_lookup_value_id` → `lookup_values.id`, `restrict`; `created_by_id`, `submitted_by_id` → `users` |
| Composite index | `(semester_id, department_id, status_lookup_value_id)` |
| Index | `status_lookup_value_id`, `instructor_id`, `section_id` |

### Relationships

`belongsTo(Semester, Course, Department, Program, Section, Instructor)`;
`belongsTo(LookupValue, 'status_lookup_value_id')` as `status`;
`hasMany(CourseOfferingApproval, ClassSchedule, ExamSchedule)`.

### Design notes

The status (a `COURSE_OFFERING_STATUS` lookup value) is the four-tier summary the list screens filter
on; the detailed decision trail lives in `course_offering_approvals`. Legal moves
(`draft → submitted → … → registrar_approved`, and any tier `→ rejected`) are guarded by
`lookup_transitions`. The offering carries no `academic_year_id` — it is reachable through
`semester_id`. The two **helper uniques** exist so `class_schedules` and `exam_schedules` can carry
composite foreign keys back to the offering's `semester_id` / `section_id`.

---

## 13. course_offering_approvals

**Purpose:** The append-only decision trail for the four-tier approval chain
(Committee → Department Head → College Dean → Registrar).
**Business justification:** Each tier records who acted, when, and why; a rejection followed by a
resubmission is genuine history a single status column cannot hold.

### Columns

    - id                        -- PK
    - course_offering_id        -- FK -> course_offerings (CASCADE)
    - level_lookup_value_id     -- FK -> lookup_values (APPROVAL_LEVEL)
    - decision_lookup_value_id  -- FK -> lookup_values (APPROVAL_DECISION)
    - sequence                  -- smallInteger, order of the entry within the offering's trail
    - acted_by_id               -- FK -> users
    - acted_at                  -- timestamp, default now
    - remark                    -- text, nullable (required on reject / revision — enforced in Form Request)
    - created_at                -- timestamp (no updated_at, no deleted_at — append-only)

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` |
| Foreign key | `course_offering_id` → `course_offerings.id`, **cascade**; `level_lookup_value_id`, `decision_lookup_value_id` → `lookup_values.id`, `restrict`; `acted_by_id` → `users.id`, `restrict` |
| Composite index | `(course_offering_id, acted_at)` — the trail in order |
| Composite index | `(acted_by_id, acted_at)` |

### Relationships

`belongsTo(CourseOffering)`; `belongsTo(LookupValue, 'level_lookup_value_id')` as `level`;
`belongsTo(LookupValue, 'decision_lookup_value_id')` as `decision`;
`belongsTo(User, 'acted_by_id')` as `actor`.

### Design notes

One table, four `APPROVAL_LEVEL` values — the tiers differ only in *who acts*, which is a value, not
a structure. Append-only: a reversal is a new row, never an edit, so there is no `updated_at` and no
soft delete. The rule "a rejection or revision request must carry a `remark`" is enforced in the
Laravel Form Request (a foreign-key column cannot express a value-conditional requirement), matching
the project's message-in-Form-Request pattern. When the Registrar tier records the `approved`
decision, the offering's status becomes `registrar_approved` — the input to both scheduling modules.

---
---

# SCHEDULING OUTPUTS

## 14. class_schedules

**Purpose:** One recurring weekly class meeting — the output of automatic class scheduling.
**Business justification:** The class timetable. An approved offering fans out into one row per
weekly meeting (Monday lecture, Wednesday lab), each placed in a room and time.

### Columns

    - id                          -- PK
    - uuid                        -- string(50), unique
    - course_offering_id          -- bigInteger (part of two composite FKs)
    - semester_id                 -- bigInteger (mirrored, composite-FK guarded)
    - section_id                  -- bigInteger, nullable (mirrored, composite-FK guarded)
    - instructor_id               -- FK -> instructors, nullable (authoritative teacher for this meeting)
    - room_id                     -- FK -> rooms, nullable
    - session_type_lookup_value_id -- FK -> lookup_values (SESSION_TYPE), nullable
    - day_of_week                 -- smallInteger, 1..7, CHECK
    - start_time                  -- time
    - end_time                    -- time, CHECK end_time > start_time
    - status_lookup_value_id      -- FK -> lookup_values (CLASS_SCHEDULE_STATUS)
    - state                       -- unsignedSmallInteger, default STATE_ACTIVE (conflict-liveness flag)
    - generation_run_id           -- FK -> schedule_generation_runs, nullable
    - created_by_id               -- FK -> users
    - published_by_id             -- FK -> users, nullable
    - published_at                -- timestamp, nullable
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid` |
| Composite FK | `(course_offering_id, semester_id)` → `course_offerings(id, semester_id)` `ON UPDATE CASCADE` |
| Composite FK | `(course_offering_id, section_id)` → `course_offerings(id, section_id)` `ON UPDATE CASCADE` |
| Foreign key | `instructor_id`, `room_id` → respective, `restrict`, nullable; `session_type_lookup_value_id`, `status_lookup_value_id` → `lookup_values.id`, `restrict`; `generation_run_id` → `nullOnDelete`; `created_by_id`, `published_by_id` → `users` |
| Exclude | `cs_no_instructor_clash`, `cs_no_room_clash`, `cs_no_section_clash` (see Conflict Prevention) |
| Index | `course_offering_id`, `(semester_id, day_of_week, start_time)`, `(room_id, day_of_week)`, `(instructor_id, day_of_week)`, `(semester_id, status_lookup_value_id)` |

### Relationships

`belongsTo(CourseOffering, Instructor, Room, ScheduleGenerationRun)`;
`belongsTo(LookupValue, 'status_lookup_value_id')` as `status`;
`belongsTo(LookupValue, 'session_type_lookup_value_id')` as `sessionType`.

### Design notes

`semester_id` and `section_id` are copies of the offering's own values, guaranteed identical by the
composite foreign keys (they cannot drift, and `ON UPDATE CASCADE` propagates any change). They
exist so the conflict `EXCLUDE` constraints can reference them on this row.

**Why `state` exists here.** The conflict `EXCLUDE` constraints must skip cancelled rows so their
slot is freed. A partial predicate cannot reference the lookup-based status without hard-coding a
lookup value id in DDL — which the project forbids (it resolves lookups by `code`, not magic id). So
a plain `state` flag (the house `STATE_ACTIVE`/`STATE_INACTIVE`) carries the "is this row live for
conflict purposes" bit that the constraint reads; cancelling a schedule sets both
`status → cancelled` **and** `state → STATE_INACTIVE` in one write. Status is the workflow label;
`state` is the constraint-visible flag.

---

## 15. exam_schedules

**Purpose:** One exam sitting — the output of automatic exam scheduling.
**Business justification:** The exam timetable. Includes an optional department-confirmation step
before publication.

### Columns

    - id                       -- PK
    - uuid                     -- string(50), unique
    - course_offering_id       -- bigInteger (composite FKs)
    - semester_id              -- bigInteger (mirrored)
    - section_id               -- bigInteger, nullable (mirrored)
    - exam_type_lookup_value_id -- FK -> lookup_values (EXAM_TYPE)
    - exam_date                -- date
    - start_time               -- time
    - end_time                 -- time, CHECK end_time > start_time
    - room_id                  -- FK -> rooms, nullable
    - required_invigilators    -- smallInteger, default 1, CHECK >= 1
    - status_lookup_value_id   -- FK -> lookup_values (EXAM_SCHEDULE_STATUS)
    - state                    -- unsignedSmallInteger, default STATE_ACTIVE (conflict-liveness flag)
    - generation_run_id        -- FK -> schedule_generation_runs, nullable
    - created_by_id            -- FK -> users
    - confirmed_by_id          -- FK -> users, nullable (department confirmation actor)
    - confirmed_at             -- timestamp, nullable
    - confirmation_remark      -- text, nullable
    - published_by_id          -- FK -> users, nullable
    - published_at             -- timestamp, nullable
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid` |
| Composite unique | `(course_offering_id, exam_type_lookup_value_id)` — one final per offering |
| Helper unique | `(id, exam_date, start_time, end_time)` — target for the assignment composite FK |
| Composite FK | `(course_offering_id, semester_id)` and `(course_offering_id, section_id)` → `course_offerings`, `ON UPDATE CASCADE` |
| Foreign key | `room_id` → `rooms.id`, `restrict`; `exam_type_lookup_value_id`, `status_lookup_value_id` → `lookup_values.id`, `restrict`; `generation_run_id` → `nullOnDelete`; user FKs → `users` |
| Exclude | `es_no_room_clash`, `es_no_section_clash` (see Conflict Prevention) |
| Index | `course_offering_id`, `(semester_id, exam_date, start_time)`, `(room_id, exam_date)`, `(semester_id, status_lookup_value_id)` |

### Relationships

`belongsTo(CourseOffering, Room, ScheduleGenerationRun)`; `hasMany(ExamInvigilatorAssignment)`;
`belongsTo(LookupValue, 'status_lookup_value_id')` as `status`;
`belongsTo(LookupValue, 'exam_type_lookup_value_id')` as `examType`.

### Design notes

`EXAM_SCHEDULE_STATUS` supports two paths — `draft → published`, or
`draft → pending_confirmation → confirmed → published` when department confirmation is required —
guarded by `lookup_transitions`. The confirmation columns capture that step. `state` plays the same
constraint-liveness role as on `class_schedules`: cancelling sets `status → cancelled` and
`state → STATE_INACTIVE`, freeing the room/section slot. `required_invigilators` sizes the
invigilation step.

---

## 16. schedule_generation_runs

**Purpose:** A record of one automatic-scheduling execution — who ran it, when, for which semester,
and its outcome.
**Business justification:** Automatic scheduling is a distinct workflow step, sometimes long-running;
this makes a run inspectable ("generated 340 rows, 6 unplaced") and gives the progress UI something
to read.

### Columns

    - id                     -- PK
    - uuid                   -- string(50), unique
    - semester_id            -- FK -> semesters
    - type_lookup_value_id   -- FK -> lookup_values (GENERATION_TYPE) class | exam
    - status_lookup_value_id -- FK -> lookup_values (GENERATION_STATUS) running | completed | failed
    - scheduled_count        -- integer, default 0 (rows produced)
    - unplaced_count         -- integer, default 0 (inputs it could not place)
    - duration_seconds       -- integer, nullable
    - summary                -- jsonb, nullable (per-run detail for the progress UI)
    - run_by_id              -- FK -> users
    - started_at             -- timestamp, nullable
    - completed_at           -- timestamp, nullable
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` · **Unique:** `uuid` |
| Foreign key | `semester_id` → `semesters.id`, `restrict`; `type_lookup_value_id`, `status_lookup_value_id` → `lookup_values.id`, `restrict`; `run_by_id` → `users.id`, `restrict` |
| Composite index | `(semester_id, type_lookup_value_id, started_at)` |

### Relationships

`belongsTo(Semester)`; `belongsTo(LookupValue, 'type_lookup_value_id')` as `type`;
`belongsTo(LookupValue, 'status_lookup_value_id')` as `status`;
`hasMany(ClassSchedule, ExamSchedule)` via nullable `generation_run_id`.

### Design notes

Schedules link back to a run (nullable), but the run holds no timetable data and nothing reads
through it on a hot path — it is telemetry. It can be dropped along with the nullable
`generation_run_id` on the schedule tables if run history is not wanted.

---
---

# INVIGILATION

## 17. invigilator_availabilities

**Purpose:** A window in which the department declares an instructor available to invigilate.
**Business justification:** The exam workflow's "Department Sends Available Invigilators" step — a
positive, dated availability window submitted by the department on the instructor's behalf.

### Columns

    - id              -- PK
    - instructor_id   -- FK -> instructors (CASCADE)
    - semester_id     -- FK -> semesters
    - available_date  -- date
    - start_time      -- time
    - end_time        -- time, CHECK end_time > start_time
    - submitted_by_id -- FK -> users (department submitter)
    - remark          -- text, nullable
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` |
| Composite unique | `(instructor_id, available_date, start_time, end_time)` |
| Foreign key | `instructor_id` → `instructors.id`, **cascade**; `semester_id` → `semesters.id`, `restrict`; `submitted_by_id` → `users.id`, `restrict` |
| Exclude | `ia_no_overlap` — no two overlapping windows for one instructor on one date |
| Index | `(instructor_id, available_date)`, `(semester_id, available_date)` |

### Relationships

`belongsTo(Instructor, Semester)`; `belongsTo(User, 'submitted_by_id')` as `submitter`.

### Design notes

A row means *available*; the absence of a row means *not offered* — a positive declaration. The
non-overlap `EXCLUDE` guarantees "is this instructor free at 09:00–12:00 on 14 June?" has exactly one
answer. This table has no status/type enum (a row is simply an availability window), so it needs no
lookup column and no `state` flag — its `EXCLUDE` applies to every row. Availability is queried
against `instructors.can_invigilate = true`.

---

## 18. exam_invigilator_assignments

**Purpose:** One instructor on duty at one exam.
**Business justification:** The output of invigilator assignment; the row that binds a person to an
exam sitting.

### Columns

    - id                     -- PK
    - exam_schedule_id       -- bigInteger (composite FK)
    - instructor_id          -- FK -> instructors
    - exam_date              -- date (mirrored from the exam)
    - start_time             -- time (mirrored)
    - end_time               -- time (mirrored)
    - role_lookup_value_id   -- FK -> lookup_values (INVIGILATOR_ROLE) chief | assistant
    - status_lookup_value_id -- FK -> lookup_values (INVIGILATION_STATUS) assigned | accepted | declined | replaced
    - state                  -- unsignedSmallInteger, default STATE_ACTIVE (conflict-liveness flag)
    - assigned_by_id         -- FK -> users
    - assigned_at            -- timestamp, default now
    - remark                 -- text, nullable
    - created_at / updated_at

### Keys, constraints, indexes

| Kind | Definition |
|---|---|
| Primary key | `id` |
| Composite unique | `(exam_schedule_id, instructor_id)` — nobody assigned twice to one exam |
| Composite FK | `(exam_schedule_id, exam_date, start_time, end_time)` → `exam_schedules(id, exam_date, start_time, end_time)` `ON UPDATE CASCADE ON DELETE CASCADE` |
| Foreign key | `instructor_id` → `instructors.id`, `restrict`; `role_lookup_value_id`, `status_lookup_value_id` → `lookup_values.id`, `restrict`; `assigned_by_id` → `users.id`, `restrict` |
| Exclude | `eia_no_double_booking` — an invigilator cannot be at two exams at once |
| Index | `exam_schedule_id`, `(instructor_id, exam_date)` |

### Relationships

`belongsTo(ExamSchedule, Instructor)`; `belongsTo(LookupValue, 'role_lookup_value_id')` as `role`;
`belongsTo(LookupValue, 'status_lookup_value_id')` as `status`;
`belongsTo(User, 'assigned_by_id')` as `assignedBy`.

### Design notes

The exam's date and times are mirrored onto the assignment and guarded by the composite foreign key,
so they cannot disagree with the exam; `ON UPDATE CASCADE` means rescheduling an exam moves every
duty row's datetime with it and re-checks the double-booking constraint against the new time
automatically. `state` is the constraint-liveness flag: a `declined` or `replaced` assignment is set
`state → STATE_INACTIVE`, so it drops out of `eia_no_double_booking` and frees the invigilator.

---
---

# CONFLICT PREVENTION

Seven database `EXCLUDE` constraints prevent every structural clash atomically — no detector service,
no race condition. Setup (one-time migration): `CREATE EXTENSION IF NOT EXISTS btree_gist;` and a
`timerange` range type over `time`.

| # | Constraint | Table | Prevents |
|---|---|---|---|
| 1 | `cs_no_instructor_clash` | `class_schedules` | Instructor teaching two overlapping classes |
| 2 | `cs_no_room_clash` | `class_schedules` | Room hosting two overlapping classes |
| 3 | `cs_no_section_clash` | `class_schedules` | A cohort in two overlapping classes |
| 4 | `es_no_room_clash` | `exam_schedules` | Room hosting two overlapping exams |
| 5 | `es_no_section_clash` | `exam_schedules` | A cohort sitting two overlapping exams |
| 6 | `ia_no_overlap` | `invigilator_availabilities` | Two overlapping availability windows for one instructor |
| 7 | `eia_no_double_booking` | `exam_invigilator_assignments` | Invigilator on duty at two overlapping exams |

Class constraints match on `(semester_id, {instructor_id \| room_id \| section_id}, day_of_week)`,
overlap on `timerange(start_time, end_time)`, and apply **`WHERE state = STATE_ACTIVE`** so cancelled
rows free their slot. Exam and assignment constraints overlap on
`tsrange(exam_date + start_time, exam_date + end_time)`, also `WHERE state = STATE_ACTIVE`.

**Why `state`, not the status lookup.** A partial `EXCLUDE` predicate must reference a value on the
row. With status held in the lookup engine, the only way to say "not cancelled" in DDL would be to
hard-code the cancelled `lookup_values.id` — the magic-id coupling the project deliberately avoids
(it resolves lookups by `code`). The `state` smallint (a standard house column) carries the
constraint-visible liveness bit; the application sets it together with the status in the same
transaction.

### Rules enforced in the service layer (not the schema)

These span two rows/tables or are intentionally overridable, so they live in the Laravel Form
Request / service, inside the same transaction as the write:

- **An invigilator must be available when assigned** — a containment check between the assignment and
  `invigilator_availabilities`.
- **A rejection/revision must carry a remark** — a value-conditional rule the foreign key cannot
  express (Form Request).
- **Room capacity ≥ expected students** — a warning; institutions overfill knowingly.
- **A lab course belongs in a lab room** — matched from the `COURSE_TYPE` vs `ROOM_TYPE` lookup
  values.
- **An instructor should not invigilate their own course** — overridable when short-staffed.
- **No scheduling on holidays / outside working hours** — an application-calendar concern.

---
---

# RELATIONSHIP MAP

```
campuses ─1:N─> buildings ─1:N─> rooms
                                   │
colleges ─1:N─> departments ─1:N─> programs ─1:N─> sections
                    │                                   │
                    ├─1:N─> courses                     │
                    └─1:N─> instructors                 │
                                                        │
academic_years ─1:N─> semesters                         │
       └────────1:N─> sections <───────────────────────┘

course_offerings ─N:1─> semesters, courses, departments
                 ─N:1─> programs, sections, instructors   (nullable)
                 ─1:N─> course_offering_approvals          (approval trail)
                 ─1:N─> class_schedules
                 ─1:N─> exam_schedules

class_schedules  ─N:1─> instructors, rooms
exam_schedules   ─N:1─> rooms
                 ─1:N─> exam_invigilator_assignments

instructors      ─1:N─> invigilator_availabilities
                 ─1:N─> exam_invigilator_assignments

schedule_generation_runs ─1:N─> class_schedules, exam_schedules   (nullable link)

lookup_values    ─referenced by─ every *_lookup_value_id (degree level, room type, course type,
                                 session type, exam type, offering/schedule/generation status,
                                 approval level & decision, invigilator role & status, …)

users            ─referenced by─ dean_user_id, head_user_id, instructors.user_id,
                                 created_by_id, submitted_by_id, acted_by_id, confirmed_by_id,
                                 published_by_id, assigned_by_id, run_by_id
```

---
---

# WORKFLOW → TABLE MAPPING

### Course offering approval (four tiers)

| Step | Table | Change |
|---|---|---|
| Committee prepares | `course_offerings` | INSERT `status = draft`, `created_by_id` |
| Submit | `course_offerings` | `status = submitted`, `submitted_by_id`, `submitted_at` |
| Department Committee | `course_offering_approvals` | INSERT `level = committee` → `status = committee_approved` |
| Department Head | `course_offering_approvals` | INSERT `level = department` → `status = department_approved` |
| College Dean | `course_offering_approvals` | INSERT `level = college` → `status = college_approved` |
| Registrar | `course_offering_approvals` | INSERT `level = registrar` → `status = registrar_approved` |
| Any rejection | `course_offering_approvals` | INSERT `decision = rejected` + remark → `status = rejected` |

(`status` and `level`/`decision` are `lookup_values`; each transition is guarded by
`lookup_transitions`.)

### Automatic class scheduling → publish

| Step | Table | Change |
|---|---|---|
| Generate | `schedule_generation_runs` + `class_schedules` | INSERT run; INSERT schedules `status = draft`, `state = ACTIVE` — constraints 1–3 fire |
| Publish | `class_schedules` | `status = published`, `published_by_id`, `published_at` |
| Cancel | `class_schedules` | `status = cancelled`, `state = INACTIVE` (slot freed) |

### Automatic exam scheduling → (confirm if required) → publish

| Step | Table | Change |
|---|---|---|
| Generate | `schedule_generation_runs` + `exam_schedules` | INSERT run; INSERT exams `status = draft`, `state = ACTIVE` — constraints 4–5 fire |
| Confirm (if required) | `exam_schedules` | `status = pending_confirmation` → `confirmed`, `confirmed_by_id` |
| Publish | `exam_schedules` | `status = published`, `published_by_id`, `published_at` |

### Invigilation

| Step | Table | Change |
|---|---|---|
| Department submits available invigilators | `invigilator_availabilities` | INSERT windows, `submitted_by_id` |
| Assign to exams | `exam_invigilator_assignments` | INSERT `status = assigned`, `state = ACTIVE` — constraint 7 fires; availability checked in-service |
