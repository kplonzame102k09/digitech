# Digitech College Portal

A Laravel-based student information portal for Digitech College. Five portal
roles (admin, teacher, student, parent, guest) share one domain model that each
page mirrors locally and pushes back over `PUT /api/portal/{collection}`. The
server is the source of truth: it enforces per-role read scoping, write-field
whitelists, and lifecycle guards on every sync.

## Roles and landing pages

| Role    | Landing page             | Typical access                                    |
|---------|--------------------------|---------------------------------------------------|
| admin   | `/admin/dashboard`       | Full CRUD, publishing, approvals, user management |
| teacher | `/teacher/dashboard`     | Own students, grades, attendance, announcements   |
| student | `/student/dashboard`     | Enrollment, requirements, documents, grades       |
| parent  | `/parent/dashboard`      | Linked children's data                            |
| guest   | `/guest/announcements`   | Prospective-student announcements & documents     |

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoUserSeeder   # optional demo accounts
npm install && npm run build                  # if assets are not prebuilt
php artisan serve
```

### Environment variables

- `DB_*` – MySQL connection (app expects MySQL; SQLite is not supported by all
  migrations).
- `APP_DEBUG` – must be `false` in production.
- `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` – use a persistent
  driver (database/redis) in production, never `file`/`array` on multi-server.
- Test suite (see below): `DB_TEST_DATABASE`.

### Demo accounts (password: `password`)

| User               | user_id          | Email                       |
|--------------------|------------------|-----------------------------|
| Registrar admin    | ADMIN-2026-000001| admin@gmail.com             |
| Teacher            | TCH-2026-000001  | teacher@school.local        |
| Student            | STU-2026-000001  | juan.delacruz@student.local |
| Student            | STU-2026-000002  | maria.reyes@student.local   |
| Student            | STU-2026-000003  | jose.garcia@student.local   |
| Parent (Juan's)    | PAR-2026-000001  | parent@school.local         |
| Guest              | GST-2026-000001  | guest@school.local          |

New/imported accounts without a real password are issued a random one and
flagged `mustChangePassword`; they are forced through `/auth/password/change`
before any role page (see `EnsurePasswordUpdated` middleware).

## Architecture notes

- **`app/Services/PortalDataService.php`** – the sync engine. `putCollection`
  (calls `syncDomainRows`/`syncUsers`) validates every row through
  `scopedDomainFields`, upserts it, then `reconcileDomainRows` treats the
  payload as the client's full mirror. Non-admins may only delete their own
  rows; **an empty/partial payload never wipes data** except for an admin's
  full replacement.
- **Alerts are server-side.** Submission/registration alerts (enrollments,
  requirements, documents, grades, parent links, teacher announcements) are
  minted by `createAdminAlert` for every active admin, honoring the
  "notify admins" setting and collapsing on `(admin, source, recordId)`.
  The old client-side observer was removed because the client users mirror no
  longer contains admins (privacy scoping).
- **Admin user-management guards.** A user sync can never delete the acting
  admin, deactivate/demote the last active admin, or delete an account that
  owns domain rows (returns `422` with the blocking ids). Deactivate instead.
- **Password minimum** is 12 characters; `login` and `uploads` routes are rate
  limited.

## Testing

### Harness suite (no DB migration, safe on live data)

Standalone PHP harnesses in the project root bootstrap the app and run inside
`DB::transaction()`/`rollback()`, so **they never modify live data**:

```bash
php test-batch1-scoping.php        # 23 checks: read/write scoping
php test-batch2-uploads.php        # 13 checks: upload/download permissions/sanitization
php test-batch3-guards.php         # 13 checks: enums, dedupe, lifecycle guards
php test-batch4-notifications.php  # 15 checks: notification forgery + dedupe
php test-batch5-admin-alerts.php   # 10 checks: server-side admin alerts
php test-batch6-guards.php         # 10 checks: admin user-sync guards
```

### PHPUnit suite

`tests/bootstrap.php` routes the suite to a **scratch database**:

```bash
php artisan test        # DB_DATABASE=digitech_portal_test when unset
DB_TEST_DATABASE=my_scratch_db php artisan test
```

**Never** run `php artisan test` against a database holding real data —
`RefreshDatabase` rebuilds the schema from migrations and destroys it. The app
DB user needs `CREATE DATABASE` privileges (or a pre-created scratch DB with
full grants). Grant example:

```sql
CREATE DATABASE IF NOT EXISTS digitech_portal_test;
GRANT ALL PRIVILEGES ON digitech_portal_test.* TO 'newuser'@'localhost';
```

## Production checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` set, `.env` not committed, `config:cache` + `route:cache`
- [ ] `SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION` on persistent drivers
- [ ] MySQL user scoped to the portal database (no `CREATE DATABASE`)
- [ ] `php artisan storage:link` (uploaded requirement files / banners)
- [ ] HTTPS termination; admin/teacher/parent accounts use 12+ char passwords
- [ ] `php artisan migrate --force` on deploy