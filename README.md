# Digitech College Portal

A Laravel-based student information portal for Digitech College. It serves five
portal roles — **admin, teacher, student, parent, guest** — from one shared
domain model. Every portal page mirrors its slice of data locally in the
browser and pushes each change back over `PUT /api/portal/{collection}`; the
server is the single source of truth, enforcing per-role read scoping, per-field
write whitelists, and lifecycle guards on every write.

---

## Table of contents

- [Tech stack](#tech-stack)
- [Roles and portals](#roles-and-portals)
- [Core concepts](#core-concepts)
- [Feature walkthrough](#feature-walkthrough)
- [Architecture](#architecture)
- [Data model](#data-model)
- [Auth & security](#auth--security)
- [Local setup](#local-setup)
- [Environment variables](#environment-variables)
- [Testing](#testing)
- [Deployment (InfinityFree)](#deployment-infinityfree)
- [Version-control hygiene](#version-control-hygiene)
- [Troubleshooting](#troubleshooting)

---

## Tech stack

| Layer       | Choice                                                              |
|-------------|---------------------------------------------------------------------|
| Backend     | Laravel 13 (`framework ^13.17`) on PHP **8.3**                      |
| Database    | MySQL (MariaDB-compatible); Philippine geo seed data included        |
| Frontend    | Server-rendered Blade + vanilla JS modules under `public/js/`        |
| CSS build   | Vite 8 + Tailwind CSS 4 (`npm run build` / `npm run dev`)             |
| Video       | Self-hosted **LiveKit** for per-classroom meetings (no recording)    |
| Icons       | `lucide` icon library                                                |
| Deploy      | InfinityFree shared hosting via `dev/*` scripts + `lftp` mirror      |

## Roles and portals

| Role    | Landing page           | Access                                                        |
|---------|------------------------|---------------------------------------------------------------|
| admin   | `/admin/dashboard`     | Full CRUD, publishing, approvals, user import, live monitor   |
| teacher | `/teacher/dashboard`   | Own classes, attendance, grades, classroom work, video        |
| student | `/student/dashboard`   | Enrollment, attendance, documents, grades, classrooms         |
| parent  | `/parent/dashboard`    | Linked child's attendance, grades, documents, announcements   |
| guest   | `/guest/announcements` | Prospective-student announcements and documents               |

Portal page routes (`routes/web.php`):

- **Admin** `/admin/*` — dashboard, users, enrollment, programs, documents,
  requirements, grades, settings, competencies, attendance (+ finalization),
  announcements, parent-links, account-requests, live-monitoring, profile.
- **Student** `/student/*` — dashboard, enrollment, attendance, documents,
  competencies, requirements, grades, announcements, profile, classrooms
  (+ classroom detail).
- **Teacher** `/teacher/*` — dashboard, students, announcements, attendance
  (+ review), competencies, grades, classrooms (+ detail), profile.
- **Parent** `/parent/*` — dashboard, children, attendance, grades, documents,
  announcements, profile.
- **Guest** `/guest/*` — announcements, documents, profile.
- **Public** — `/` (landing), `/auth/login`, `/request-account`,
  `/classroom/join/{token}` (invite link).

## Core concepts

### Mirror-and-sync domain model

Each client page boots with a scoped "mirror" of its domain collections
(users, students, enrollments, grades, competencies, announcements,
attendances, document requests, requirements, …). The client reads through
`DG.getData()` and writes through `PUT /api/portal/{key}`. The server:

1. Validates every row via `scopedDomainFields` (per-role read scopes + per-role
   write-field whitelists).
2. Upserts the payload row-by-row in `syncDomainRows` / `syncUsers`.
3. Reconciles the collection against the client's full mirror —
   `reconcileDomainRows`. **An empty or partial payload never wipes data**;
   only an admin's full replacement reconciles, and non-admins can delete only
   their own rows.

### Dedicated REST modules (not mirrored)

Classrooms, classroom activities/submissions/gradebook, attendance sessions and
packages, account requests, admin profile, and analytics are handled by their
own REST controllers instead of bulk sync — see
[Feature walkthrough](#feature-walkthrough).

### Server-side admin alerts

Submission/registration events (enrollments, requirements, documents, grades,
parent links, teacher announcements) mint a row via `createAdminAlert` for every
active admin, honoring the "notify admins" setting and collapsing on
`(admin, source, recordId)`. The old client-side observer was removed because
the client users mirror no longer contains admins (privacy scoping).

### Analytics

Additive server-side analytics endpoints exist alongside the legacy bulk PUT
sync: attendance summary (global and by recorder) and grade summaries with a
preview-save flow (`GradeAnalyticsController::summary` / `::preview`).

## Feature walkthrough

**Account lifecycle**

- Public self-registration via `/request-account` (rate limited); admins
  approve/reject via `/admin/account-requests`.
- User import: `POST /api/portal/users/import` with batch status polling.
- New/imported accounts without a real password are issued a random one,
  flagged `mustChangePassword`, and forced through `/auth/password/change`
  before any role page (`EnsurePasswordUpdated`).
- Passwords: minimum **12 characters**, `login`/`uploads`/`account-requests` and
  classroom-join routes are rate limited.

**Academic records**

- Term-based grading: `prelim` (20%) / `midterm` (30%) / `finals` (50%), two
  semesters, with a final-grade summarizer and grade-key uniqueness.
- Competency framework: teacher-assessed competencies with an assessor, per
  student.
- Enrollments with assigned teacher and a `EnsureStudentEnrollment` middleware
  that gates student pages until enrolled.

**Attendance**

- Per-classroom session funnel: **start → mark → verify → submit → export**,
  students recorded against a classroom and subject (`recordedBy` tracked).
- Advisory **review** flow: filters, day review, status edits, submit-to-admin.
- Admin **finalization**: packages submitted by advisers get accepted or
  `return`ed for correction; finalized packages may be returned too.
- Attendance is time-tracked and self-locking; the admin live-monitoring page
  exposes a live activity stream plus an unlock control.

**Classrooms (teacher-owned)**

- Teacher creates classrooms; students join by access code or invite token
  (`/classroom/join/{token}`).
- Activities (fixed classroom subject), student submissions with scoring /
  return / CSV export, and a per-student gradebook.
- Classroom files live on the **private** disk behind ownership checks.
- Video meetings: teacher schedules/starts/ends/cancels; server-minted LiveKit
  join tokens only (`VideoTokenService`, hand-rolled HS256 JWT — no extra deps).
  Teachers get room-admin grants; no recording.

**Documents & requirements**

- Students request documents (`document_requests`); requirement files upload
  against the protected disk and download only with permission.

**Announcements & settings**

- Admin publishes global announcements (with image + author); system settings
  include catalogues, and the "notify admins" toggle.

## Architecture

### Request pipeline (server)

```
web.php → middleware: auth → password.updated → role:{admin|student|teacher|parent|guest}
        → (student only) student.enrolled → controller → PortalDataService
```

| Middleware             | Purpose                                                        |
|------------------------|----------------------------------------------------------------|
| `auth`                 | Laravel session auth                                            |
| `password.updated`     | Forces the must-change-password flow                            |
| `role:{...}`           | `EnsureUserRole` route guard                                    |
| `student.enrolled`     | `EnsureStudentEnrollment` blocks pre-enrollment access          |
| `TrackActivity`        | Writes activity log rows (admin monitoring feed)                |

### Server components

- **`app/Services/PortalDataService.php`** — the sync engine: scope validation,
  upsert, reconcile, photo/requirement uploads, admin alert minting.
- **`app/Services/Attendance*Service.php`** — session marking, review edits,
  analytics rollups.
- **`app/Services/VideoTokenService.php`** — LiveKit HS256 JWT minting.
- **`app/Http/Controllers/StorageFileController.php`** — serves public-disk
  uploads via `GET /storage/{path}` on shared hosts that have no symlinks
  (path-traversal and dotfile rejected, immutable browser caching).
- **`app/Support/LoginAttemptGuard.php`** / **`ReferenceCode.php`** — login
  throttling helpers and human-friendly reference-code generation.

### Client (vanilla JS, no framework)

`public/js/` is organized around a global singleton `window.DG`:

| Module              | Role                                                        |
|---------------------|-------------------------------------------------------------|
| `api.js`            | Fetch wrapper (credentials, CSRF, error normalization)      |
| `data.js`           | `DG.getData` / `DG.saveData` localStorage mirror            |
| `sync.js`           | Boot snapshot, `DG.importData`, notification dedupe         |
| `features.js`       | Shared feature logic (final grades, avatars, CSV export)    |
| `workflows.js`      | Cross-entity workflows (e.g. enrollment → documents)        |
| `storage.js`        | Upload pipeline with race-safe file recording               |
| `app.js` / `auth.js`| Shell behaviours, auth page logic                           |
| `profile.js`        | Photo URL normalization + avatar rendering everwhere        |
| `admin|teacher|student|parent|guest/` | Role-specific page controllers  |

Pages are Blade shells that mostly render into a container; the matching JS
read the mirror, render markup, and push changes back through the API.

## Data model

Migrations in `database/migrations/` group into:

- **Framework** — cache, jobs, sessions, users (+ parent links
  `parent_student`).
- **System** — `portal_collections` (raw mirror tables), `system_settings`,
  `activity_logs`, account requests.
- **Philippine geo** — regions, provinces, cities, barangays (seeded).
- **Academics** — enrollments, grades (term-based, unique keys), attendance
  (subject/recorder, time tracking, sessions, packages, finalization),
  competencies, document requests, requirements (file URL), announcements
  (image + author).
- **Classrooms** — classrooms, classroom students, activities, submissions,
  meetings (room name nullable), classroom files.

Seeders ship full Philippine region/province/city/barangay data plus an admin
(see `ADMIN_SEED_*` env vars).

## Auth & security

- Session-based auth (`SESSION_DRIVER=database` in production), rate-limited
  login and upload routes.
- Per-role read scopes and write-field whitelists on every sync write.
- **Admin user-management guards:** a user sync can never delete the acting
  admin, deactivate/demote the last active admin, or delete an account that
  owns domain rows (returns `422` with the blocking ids) — deactivate instead.
- Password minimum 12 chars; forced password reset for weak/imported accounts.
- LiveKit tokens minted server-side only; classroom file downloads require
  ownership.
- `SETUP_TOKEN` guards the one-shot migrator/seeder so a deploy cannot be
  re-seeded by strangers.
- Shared-host hardening: `.htaccess` denies dotfiles, static assets alias
  `public/`, and the front controller boots Laravel. Uploads are served through
  PHP (`StorageFileController`) — a `^storage/` **rewrite rule is forbidden**
  (it loops on shared Apache).

## Local setup

```bash
# everything (install, .env, key, migrate+seed, npm build)
composer setup

# or step by step:
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install && npm run build          # frontend assets
php artisan serve                     # http://localhost:8000
```

MySQL is required (`DB_*`); SQLite is not supported by all migrations. The
seeded admin logs in with `ADMIN_SEED_EMAIL`/`ADMIN_SEED_PASSWORD` from the env.

## Environment variables

See `.env.example` for the full list with defaults. Grouped reference:

| Group        | Keys (interesting ones)                                          |
|--------------|------------------------------------------------------------------|
| App          | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL`, `APP_MAINTENANCE_DRIVER` |
| Admin seed   | `ADMIN_SEED_USER_ID`, `ADMIN_SEED_EMAIL`, `ADMIN_SEED_PASSWORD`  |
| Security     | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SETUP_TOKEN` |
| Database/queue | `DB_*`, `CACHE_STORE`, `QUEUE_CONNECTION`                       |
| Video        | `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`           |
| Mail         | `MAIL_*` (mailer, host, credentials, from)                        |
| Misc         | `FILESYSTEM_DISK`, `BCRYPT_ROUNDS`, `LOG_*`                       |

Rules of thumb:

- `APP_DEBUG=false` everywhere but local; `SESSION_SECURE_COOKIE=true` on HTTPS.
- Production drivers: `SESSION_DRIVER=database`, `CACHE_STORE=database`,
  `QUEUE_CONNECTION=sync` (InfinityFree has no job worker/cron; the app never
  depends on a queue worker).
- On InfinityFree, outbound SMTP is blocked — every `Mail::` call site is inside
  `try/catch` + `Log::warning`, so account creation/login never 500s.

## Testing

### PHPUnit suite

`tests/bootstrap.php` routes the suite to a **scratch database**:

```bash
php artisan test            # uses digitech_portal_test when unset
DB_TEST_DATABASE=my_scratch_db php artisan test
```

**Never** run `php artisan test` against a database with real data —
`RefreshDatabase` rebuilds the schema and destroys it. The app DB user needs
`CREATE DATABASE` (or a pre-created scratch DB with full grants):

```sql
CREATE DATABASE IF NOT EXISTS digitech_portal_test;
GRANT ALL PRIVILEGES ON digitech_portal_test.* TO 'portal_user'@'localhost';
```

### Standalone harnesses (safe on live data)

Root-level `test-batch*.php` scripts bootstrap the app inside
`DB::transaction()`/`rollback()`, so they never modify data:

```bash
php test-batch1-scoping.php                # 23 checks: read/write scoping
php test-batch2-uploads.php                # 13 checks: upload/download perms/sanitization
php test-batch3-guards.php                 # 13 checks: enums, dedupe, lifecycle guards
php test-batch4-notifications.php          # 15 checks: notification forgery + dedupe
php test-batch5-admin-alerts.php           # 10 checks: server-side admin alerts
php test-batch6-guards.php                 # 10 checks: admin user-sync guards
php test-batch7-enrollment-guards.php      # 38 checks: enrolment-gate routes + roster scoping
```

## Deployment (InfinityFree)

Production runs on InfinityFree shared hosting: PHP 8.3, MySQL via the panel's
`sql*.infinityfree.com` host, **no SSH/shell access, no cron, outbound SMTP
blocked.** Because there is no shell on the server, deployment is done from this
machine with the scripts under `dev/`.

### Daily push

```bash
dev/infinityfree-sync.sh            # rebuild + upload only changed files
dev/infinityfree-sync.sh --prune    # also delete remote files you removed locally
dev/infinityfree-sync.sh --dry-run  # preview (needs lftp with --dry-run support)
```

`infinityfree-sync.sh` always regenerates the `infinityfree/` tree from scratch
(`dev/infinityfree-prepare.sh`: hardlinks app source, `composer install
--no-dev`, writes an INF-ready `.env`), overlays `live.env` on top (so the
production `APP_KEY`, real DB credentials and `SETUP_TOKEN` survive every
deploy — never regenerate `APP_KEY` or everyone is logged out), then lftp-
mirrors only changed files to `htdocs/`.

Safety guarantees:

- Requires `lftp` (`sudo apt install lftp`) and `.ftp-inf` with real FTP
  credentials (`INF_FTP_HOST` / `INF_FTP_USER` / `INF_FTP_PASS` /
  `INF_FTP_DIR=htdocs`).
- `storage/`, `.git/` and `public/storage/` are **excluded**: server uploads and
  runtime data are never overwritten or deleted. Files are only removed from the
  server with `--prune`.
- Each run re-lists the remote tree over FTP, so it takes a few minutes even
  when nothing changed.
- `dev/live-deploy.sh` is the legacy full-zip fallback (`infinityfree-deploy.zip`
  + curl FTP) for one-off full refreshes.

### First-time provisioning on INF

1. Enable MySQL in the INF control panel and copy the credentials into
   `live.env` (`DB_HOST` is `sql*.infinityfree.com`, **not** `localhost`).
2. Run `dev/infinityfree-sync.sh`.
3. Open `https://<app-url>/setup_seed.php?token=<SETUP_TOKEN>` once — it runs
   `php artisan migrate --force` + `db:seed` over HTTPS.
4. Delete `public/setup_seed.php` from the server and change
   `ADMIN_SEED_PASSWORD` in `live.env` before real use.
5. Any later schema change: push, re-run the seeder once, delete it again.

### Uploads on shared hosts

- The public disk root is `storage_path('app/public')`; on hosts without
  symlinks there is **no** `storage:link` — files are served through
  `StorageFileController` (`GET /storage/{path}`) with immutable cache headers
  and traversal protection.
- The web-root `.htaccess` generated by `infinityfree-prepare.sh` must **not**
  contain a `^storage/` rewrite rule (it internal-loops on shared Apache —
  see [Troubleshooting](#troubleshooting)).
- Locally, `php artisan storage:link` restores the `public/storage` symlink via
  the Laravel `links` config.

## Version-control hygiene

Gitignored and never to be committed:

```
live.env            # production secrets (APP_KEY, DB creds, SETUP_TOKEN)
.ftp-inf            # FTP credentials for the sync scripts
/infinityfree       # build artifact, regenerated by infinityfree-prepare.sh
/infinityfree-deploy.zip  # legacy bundle
digitech-portal-1.0.0.webc  # old Wasmer artifact
/deploy             # old Wasmer Edge stage tree
database/database.sqlite    # dev sqlite
public/hot, public/fonts-manifest.dev.json   # Vite dev artifacts
test-*.php          # harnesses stay local by policy
```

## Troubleshooting

| Symptom                                            | Cause / fix                                                        |
|----------------------------------------------------|--------------------------------------------------------------------|
| Avatar/photo stays default after "upload success"  | A `^storage/` rewrite rule in the live `.htaccess` loops on shared Apache → 500. Remove it; uploads are served by `/storage/{path}` PHP route. |
| `GET /storage/...` returns 403                     | Old signed `storage.local` route — the `local` disk must not set `'serve' => true`. |
| `/setup_seed.php` says forbidden                   | Wrong/expired `SETUP_TOKEN`; value comes from `live.env`, never commit it. |
| Login for everyone fails the same day              | `APP_KEY` was regenerated on deploy — overlay `live.env` so it stays stable. |
| Mail-dependent flow fails                          | INF blocks SMTP; failures are logged, not fatal. Set `MAIL_MAILER=log` to silence. |
| `git push` doesn't change the live site            | `infinityfree/` is a gitignored build artifact — run `dev/infinityfree-sync.sh` instead. |
| Sync takes minutes with nothing to change          | First-pass FTP listing of ~7,700 files; only changed bytes transfer. |