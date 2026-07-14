# Schedule — Backend (Laravel)

A **starter kit** for a class & exam schedule system ERP backend. It keeps a complete, production-shaped **authentication + flat
RBAC** foundation and drops everything ERP-specific (entities, parties,
descendants, modules/features, custom fields, tax, documents, subscriptions,
inventory, sales).

`User` is the master table. Role/permission assignment is **flat/global** — a
user simply holds a role or a permission override, with no entity/tenant
scoping.

## Stack

- Laravel 13 (PHP 8.2+), Laravel Passport (OAuth2 password grant)
- PostgreSQL
- Pest 3 / PHPUnit 11
- Bilingual (English / Amharic) translation engine

## What's included

- **Auth:** login, logout, password reset + OTP, two-factor (TOTP + backup
  codes), device/session management.
- **Users:** CRUD, profile, status/state toggles, activity logs.
- **Roles:** CRUD, system vs custom, permission assignment.
- **Permissions & permission groups:** CRUD, grouped catalogue.
- **Flat RBAC engine:** `user_role_bindings` (`user_id` + `role_id`) and
  `user_permission_overrides` (`user_id` + `permission_id` + `allow`), cached
  per user. See `helper/Permission/` and `helper/Cache/`.
- **Lookup engine:** generic `LookupType` / `LookupValue` / `LookupTransition`
  (dynamic dropdowns + status flows).
- **`/user/allowed-routes`:** returns the permission-filtered route list,
  encrypted action list, and data-driven sidebar the frontend consumes.
- **Sample feature — class & exam schedules** (`app/Models/Schedule`,
  `/api/schedule/class-schedules`): a full vertical slice (migration → model →
  Form Request → service → controller → routes → permissions → seeder) showing
  the project's conventions, including a room/time conflict business rule. Use
  it as the template for new features; delete it if you don't need it.

## Setup

```bash
composer install
cp .env.example .env          # then set DB + APP_KEY
php artisan key:generate

# PostgreSQL: create the database named in constants/AppConstant.php
#   (default connection = schedule_user)
createdb schedule_user        # or: CREATE DATABASE schedule_user;

php artisan migrate:fresh --seed
php artisan passport:keys --force
php artisan passport:client --personal --name="Schedule Personal"

php artisan serve             # http://127.0.0.1:8000
```

> The default DB connection, name, and credentials live in
> `constants/AppConstant.php` (copy from the `*.example.php` sibling
> and keep it out of git). `.env` `DB_*` values feed the fallback `pgsql`
> connection.

## Seeded sample accounts

All accounts log in with the password **`schedulePwd`** (username = email):

| Email | Role | Notes |
|---|---|---|
| `admin@schedule.com` | Super Admin | full access |
| `registrar@schedule.com` | Registrar | most user-management + schedule perms |
| `teacher@schedule.com` | Teacher | read-only |
| `student@schedule.com` | *(none)* | demo of an unassigned user |

## Adding a feature

Mirror the sample schedule slice and follow the conventions in `CLAUDE.md`
§10 (Form Request per write endpoint; service returns translation-key strings
on business errors; `ScopedModel` + flat `applyRoleBasedQuery`; permission keys
in `helper/Permission/PermissionList.php`; bilingual translations).

## Layout

```
app/                 Controllers, Requests, Services, Models (User, Role, Permission, Schedule)
helper/              Field DSL, Response envelope, SideBar builder, Permission engine + cache, Types
constants/           AppConstant (DB connection, git-ignored)
translations/        Back / Front / Message / Sidebar (en + am)
database/            migrations, factories, seeders
routes/api.php       auth + account + user + role + permission + lookup + schedule
```
