# N Events

Personalized event discovery platform for Tamil Nadu — tech meetups, startup events, workshops, cycling rides and cultural events, ranked and delivered to each user instead of requiring them to search.

> "Stop searching for events. Let the right events find you."

## Status

Actively developed. The public site, authentication, event discovery/search, saved events, **logged-in user event posting** (create / edit / cancel / My Events), moderation and reports, **email notifications** (digests, reminders, event updates — delivered by a background scheduler), the admin panel and the **multi-source discovery pipeline** (web JSON-LD, sitemaps, RSS, approved social APIs, licensed search) are functional against a real MySQL database. Social and search connectors are implemented but **stay disabled until their API credentials are supplied** — they have been exercised against API-shaped test fixtures, not live platform APIs. Google Calendar works via pre-filled links and `.ics`; OAuth calendar sync is not implemented. See [Known Gaps](#known-gaps--whats-not-finished).

## Key Features

- **All of India**: 28 states and 8 union territories (Puducherry included), their 780 districts and about 1,000 cities and major places, supported everywhere (homepage, dashboard, onboarding, search, posting, admin) from one shared source — see [Locations Across India](#locations-across-india)
- Personalized event discovery by district, category, date and format (online/offline/hybrid)
- Full-text + filtered event search, "Today" / "This Weekend" / "Free" / "Online" quick views
- User accounts with saved events, interests, email notification preferences and onboarding; **WhatsApp/mobile number collected at signup only** (never messaged) — see [WhatsApp Number](#whatsapp-number)
- **Post an Event** — any logged-in user can publish events that appear in the same lists as discovered events, and manage them from **My Events** — see [User Event Posting](#user-event-posting)
- **Email notifications** — daily/weekly digests, saved-event reminders, event updated/postponed/cancelled, submission status — sent by a background scheduler, never inside a page request — see [Notifications](#notifications-email--google-calendar)
- **Multi-source discovery** — organizer/event/college/community websites (schema.org Event JSON-LD, XML sitemaps, RSS) plus Instagram / Facebook / X connectors that use only approved APIs; every hit is a candidate first, validated, located, de-duplicated across sources into one canonical event — see [Event Discovery Sources](#event-discovery-sources)
- Report Event, admin moderation (publish / unpublish / flag / reject / suspend / cancel, suspend a submitter's posting rights), duplicate-review queue, audit log
- Organizer portal (dashboard, organizer event list; event creation uses the shared Post an Event flow)
- Admin panel (event moderation, reports, duplicates, users, source management, dashboard metrics)
- **Android & iOS app** — a Capacitor app in `mobile/` that opens the live site, with an offline screen and native sharing; builds a signed .apk / .aab on Windows and an .ipa on a Mac — see [mobile/README.md](mobile/README.md)
- SSRF-guarded "Register Now" redirect that never trusts an arbitrary destination URL
- CSRF protection, session-based auth, Argon2id/bcrypt password hashing
- Global error handler — uncaught exceptions and fatal errors render a branded page (or JSON for API/AJAX), never a raw PHP stack trace, and are logged to `storage/logs/`
- Real SMTP email verification (PHPMailer via a central `MailService`) with resend, rate-limiting, and a configurable verification requirement — see [Email Verification & Login Policy](#email-verification--login-policy)
- Every event card and the event detail hero always show an image — a real one when the event has one, otherwise on-brand category artwork, never a broken image icon — see [Event Images](#event-images)

## Technology Stack

| Layer     | Technology |
|-----------|------------|
| Frontend  | HTML5, CSS3 (N Events design system in `public/css/theme.css`), Bootstrap 5, vanilla JavaScript (Fetch API) |
| Backend   | PHP 8.2+, a small custom MVC framework (router, DI container, middleware — no external framework) |
| Database  | MySQL 8.0+ / MariaDB, accessed via PDO with prepared statements |
| Mail      | PHPMailer (SMTP) |
| Other     | vlucas/phpdotenv (env config), ramsey/uuid, league/commonmark, monolog |

This is **not** Laravel/Symfony/Slim — `app/Core/` (`Router`, `Container`, `Application`, `Request`, `Response`, `View`) is a purpose-built, lightweight MVC layer. Keep that in mind when reading the code: e.g. `Connection::insert()/update()/delete()` take a **raw SQL string + bindings**, not an Eloquent-style `(table, data)` pair.

## Requirements

- **PHP 8.2+** with extensions: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json`, `fileinfo` (all bundled with a standard XAMPP/WAMP install)
- **MySQL 8.0+** or **MariaDB 10.4+**
- **Composer 2.x**
- A modern browser (Chrome, Firefox, Edge, Safari — current versions)

Optional:
- SMTP credentials — needed for any email to actually be delivered (verification, password reset, digests, reminders)
- Approved API access for any social connector you want to enable (Meta app + tokens for Instagram/Facebook, a paid X API tier for X) — see [Event Discovery Sources](#event-discovery-sources)

**Not needed:** no WhatsApp Business account, WhatsApp API or WhatsApp credentials of any kind.

## Quick Start (Windows / XAMPP)

```bash
composer install
copy .env.example .env
```

Edit `.env` — at minimum set `DB_PASSWORD` to your MySQL root password (XAMPP's default is usually empty) and pick one of the two `APP_URL` styles described in [Running the App](#running-the-app) below.

```bash
php bin/migrate.php
php bin/create-admin.php
php bin/create-demo-user.php   # optional: demo@nevents.local / DemoPass123 (refuses when APP_ENV=production)
```

Then start the server (see the two options below) and visit the site.

## Installation (detailed)

1. **Clone/copy the project** into your web root, e.g. `C:\xampp\htdocs\Lordminds\N_Events`.
2. **Install PHP dependencies:**
   ```bash
   composer install
   ```
3. **Create your environment file:**
   ```bash
   copy .env.example .env
   ```
   (macOS/Linux: `cp .env.example .env`)
4. **Configure `.env`** — see [Environment Variables](#environment-variables) below. The two settings you must get right for a local install are `DB_PASSWORD` and `APP_URL`.
5. **Create the database and run migrations + seed data:**
   ```bash
   php bin/migrate.php
   ```
   This creates the `nevents` database if it doesn't exist, runs every file in `database/migrations/` (idempotent — safe to re-run), then every file in `database/seeds/` (also safe to re-run). The base seed loads roles/permissions, Tamil Nadu's cities/districts, and the category tree. A second seed (`002_seed_demo_events.sql`) adds a handful of real sample organizers/venues/events so the site isn't empty on first run — delete that file before seeding if you don't want demo content.
6. **Create your admin account:**
   ```bash
   php bin/create-admin.php
   ```
   You'll be prompted for a name, email and password. This creates an active, pre-verified `super_admin` account — there is no public "become an admin" flow, by design.
7. **Start the app** — see below.
8. **Sign in** at `/login` with the admin account, and visit `/admin`.

## Running the App

You have two supported options. Pick one.

### Option A — PHP's built-in server (recommended for local development)

```bash
php -S localhost:8080 -t public router.php
```

`router.php` (at the project root) is required — without it, PHP's built-in server 404s on any URL with a file extension it doesn't recognize (`/sitemap.xml`, `/robots.txt`), because it tries to serve those as static files instead of routing them to the app. With the router script, static assets (`css/js/images`) are still served directly and everything else is handed to `public/index.php`, matching production behavior.

Set in `.env`:
```
APP_URL=http://localhost:8080
```

Then visit **http://localhost:8080**.

### Option B — XAMPP's Apache

Because `app/`, `config/`, `database/`, `vendor/` and `.env` live outside `public/`, Apache must be pointed at the `public/` folder specifically — not the project root.

**B1. Simplest — access the `public/` subfolder directly** (no vhost needed): with the project at `C:\xampp\htdocs\Lordminds\N_Events`, set:
```
APP_URL=http://localhost/Lordminds/N_Events/public
```
and visit that URL. `public/.htaccess` (mod_rewrite) routes every request to `index.php`; make sure Apache's `httpd-xampp.conf`/`httpd.conf` has `AllowOverride All` for `htdocs`, and that `mod_rewrite` is enabled (both are XAMPP defaults).

**B2. Cleaner — a dedicated virtual host** pointing `DocumentRoot` at the `public/` folder, e.g.:
```apache
<VirtualHost *:80>
    ServerName nevents.local
    DocumentRoot "C:/xampp/htdocs/Lordminds/N_Events/public"
    <Directory "C:/xampp/htdocs/Lordminds/N_Events/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Add `127.0.0.1 nevents.local` to your hosts file, set `APP_URL=http://nevents.local`, restart Apache.

Start Apache and MySQL from the XAMPP Control Panel either way.

## Environment Variables

All variables live in `.env` (never commit this file — `.env.example` is the template with placeholders only).

| Variable | Purpose |
|---|---|
| `APP_NAME` | Display name, used in page titles/emails |
| `APP_ENV` | `development` or `production` |
| `APP_URL` | Base URL — **must match how you're actually serving the app**, see above |
| `APP_SECRET` | Set to a random 32+ character string; currently unused by the code but reserved — set it anyway |
| `APP_DEBUG` | `true` shows verbose errors locally; **must be `false` in production** |
| `APP_TIMEZONE` | Default `Asia/Kolkata` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`, `DB_COLLATION` | MySQL connection — read by `config/database.php` via PDO |
| `SESSION_NAME`, `SESSION_LIFETIME`, `SESSION_SECURE`, `SESSION_HTTPONLY`, `SESSION_SAMESITE` | PHP session cookie config. Set `SESSION_SECURE=true` once you're on HTTPS |
| `MAIL_PROVIDER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Real SMTP settings sent through PHPMailer by `MailService` — see [Email Verification & Login Policy](#email-verification--login-policy). Leave `MAIL_HOST`/`MAIL_USERNAME` blank locally and the app degrades safely: the account still gets created, nothing is ever falsely reported as "sent", and the failure is logged |
| `REQUIRE_EMAIL_VERIFICATION`, `VERIFY_TOKEN_TTL_HOURS`, `RESEND_VERIFICATION_COOLDOWN_SECONDS` | Central verification policy read by `AuthService` — see below |
| `USER_EVENT_MAX_PER_HOUR`, `USER_EVENT_MAX_PER_DAY` | Posting rate limits per account (defaults 5 / 20). Admins and moderators are exempt |
| `EVENT_IMAGE_MAX_MB` | Max event poster size (default 5). Also bounded by PHP's `upload_max_filesize`/`post_max_size` |
| `REPORT_MAX_PER_DAY` | Max "Report Event" submissions per user per day (default 10) |
| `DISCOVERY_AUTO_PUBLISH_MIN_CONFIDENCE`, `DISCOVERY_AUTO_PUBLISH_MIN_TRUST` | A discovered candidate is published automatically only if extraction confidence ≥ the first (default 80) AND the source's `trust_level` ≥ the second (default 70); anything else waits for review |
| `DISCOVERY_USER_AGENT` | User-Agent the crawler/feeds send and match against robots.txt groups (default `NEventsBot/1.0 (event discovery; +APP_URL/about)`) |
| `DISCOVERY_MIN_DELAY_MS` | Minimum pause between two requests to the same host (default 1500). A larger robots.txt `Crawl-delay` wins (capped at 30 s) |
| `DISCOVERY_MAX_PAGES` | Default page budget per crawler source per run (default 40; `config_json.max_pages` overrides) |
| `DISCOVERY_TEST_HOSTS` | Comma-separated `host:port` list the adapters may fetch even though it's private/local — **for the test suite only, ignored when `APP_ENV=production`**. Leave empty |
| `SOCIAL_RAW_RETENTION_DAYS` | Stored social post text is erased from processed candidates after this many days (default 30) |
| `META_GRAPH_API_VERSION` | Graph API version for Instagram/Facebook calls (default `v21.0` — set to a version your Meta app supports) |
| `INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_ACCOUNT_IDS` | Instagram Graph API — token for organizer-authorized professional accounts + comma-separated IG user IDs. Empty = connector disabled |
| `FACEBOOK_PAGE_ACCESS_TOKEN`, `FACEBOOK_PAGE_IDS` | Facebook Graph API — Page token + comma-separated Page IDs. Empty = connector disabled |
| `X_BEARER_TOKEN` | X API v2 bearer token (tier must include recent search). Empty = connector disabled |
| `SEARCH_PROVIDER` | `none` (default). No licensed search provider is bundled — see [Event Discovery Sources](#event-discovery-sources) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `FEATURE_GOOGLE_CALENDAR_OAUTH` | Reserved. OAuth calendar sync is **not implemented**; "Add to Google Calendar" needs none of these |
| `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL` | Optional AI-assisted event-data extraction — only used if `FEATURE_AI_EXTRACTION=true` |
| `SEARCH_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | Search currently always runs against MySQL FULLTEXT; the Meilisearch variables are reserved for a future driver and unused today |
| `CACHE_DRIVER`, `CACHE_TTL` | Reserved; no caching layer is wired up yet |
| `STORAGE_DRIVER`, `UPLOAD_MAX_SIZE_MB` | File upload config (`config/app.php`) |
| `FEATURE_*` | Feature flags — see `config/app.php`. All default to safe/off except `FEATURE_ORGANIZER_PORTAL` |
| `RATE_LIMIT_LOGIN`, `RATE_LIMIT_WINDOW` | Login attempt throttling |
| `LOG_LEVEL`, `LOG_CHANNEL` | Monolog config |

Never put real secrets in this README or in `.env.example` — only placeholders.

## Database Setup & How to View Data

### Where the database actually lives

This app uses **MySQL** (or MariaDB) — a real database *server* process, not a file inside the project. There is no `database.db`/SQLite file to go looking for. Concretely, based on the actual project config (`config/database.php`, reading from `.env`):

- **Engine:** MySQL 8.0+ / MariaDB (via PDO, `config/database.php`)
- **Host:** `DB_HOST` in `.env` — `127.0.0.1` by default, i.e. the MySQL server bundled with your local XAMPP/WAMP install, listening on `DB_PORT` (default `3306`)
- **Database name:** `DB_DATABASE` in `.env` — `nevents` by default
- **Credentials:** `DB_USERNAME`/`DB_PASSWORD` in `.env` — never printed here or anywhere in the repo; XAMPP's default is `root` with an empty password, which is fine for local dev but should never be used in production

The schema (`database/migrations/001_create_core_tables.sql`) is a single, comprehensive migration covering users/roles, geography (country → state → district → city → area → venue), categories/tags, events (with occurrences, images, speakers, agenda), organizers, event-source ingestion tables, saved events/interactions, notifications, calendar sync, and admin/audit tables — 50+ tables, all `InnoDB`/`utf8mb4`, with foreign keys and indexes on the columns actually queried.

`php bin/migrate.php` is the only tool you need — it creates the database, applies migrations, and applies seeds, all idempotently (`CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`), so re-running it on an existing install is safe and won't duplicate data.

If you'd rather do it by hand:
```sql
CREATE DATABASE nevents CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
then import `database/migrations/001_create_core_tables.sql` followed by `database/seeds/001_seed_base_data.sql` and (optionally) `database/seeds/002_seed_demo_events.sql`, in that order, via phpMyAdmin or `mysql < file.sql`.

### Viewing data with phpMyAdmin (XAMPP)

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open **http://localhost/phpmyadmin**.
3. In the left sidebar, click the `nevents` database (matches `DB_DATABASE`).
4. Click the **`users`** table → **Browse** to see every registered account.
5. Newly registered users are the rows with the highest `id` / most recent `created_at` — sort by clicking the `created_at` column header, or use the SQL tab with the query below.

### Viewing data with the MySQL CLI

```bash
mysql -u root -p
```
(enter your `DB_PASSWORD`; press Enter if it's blank, matching XAMPP's default)

```sql
SHOW DATABASES;
USE nevents;
SHOW TABLES;
DESCRIBE users;

-- Most recently registered users (never selects password_hash for display)
SELECT id, name, email, phone, whatsapp_number, email_verified_at, status, created_at
FROM users
ORDER BY id DESC
LIMIT 20;

-- Confirm a specific signup actually saved, and check its pending verification token
SELECT id, name, email, email_verified_at, created_at FROM users WHERE email = 'someone@example.com';
SELECT type, expires_at, used_at, created_at FROM auth_tokens
WHERE user_id = (SELECT id FROM users WHERE email = 'someone@example.com')
ORDER BY created_at DESC;
```

### Which tables/columns matter for registration

| What | Table | Key columns |
|---|---|---|
| Registered users | `users` | `id`, `uuid`, `name`, `email` (unique), `password_hash` (Argon2id/bcrypt — never plaintext), `whatsapp_number` (the signup number, E.164 — `phone` is legacy and no longer written), `status`, `can_post_events`, `created_at` |
| Posted events | `events` | `created_by_user_id`, `data_origin`, `status`, `moderation_status`, `moderation_reason`, `published_at` — see [User Event Posting](#user-event-posting) |
| Email verification state | `users` | `email_verified_at` — `NULL` = unverified, a timestamp = verified (this project uses a nullable datetime, not a `is_verified` flag) |
| Verification / password-reset tokens | `auth_tokens` | `user_id`, `type` (`email_verify` or `password_reset`), `token_hash` (SHA-256 of the raw token — the raw token only ever exists in the emailed link), `expires_at`, `used_at` |
| Login throttling | `login_attempts` | `identifier`, `ip_address`, `success`, `attempted_at` |

### Confirming a registration actually persisted

After signing up, run the two queries under "Viewing data with the MySQL CLI" above (substituting the real email). You should see: a `users` row with the right email/phone, `password_hash` starting with `$argon2id$` or `$2y$` (never the plaintext password), `email_verified_at` as `NULL`, and an `auth_tokens` row with `type = 'email_verify'`, `used_at` still `NULL`, and `expires_at` roughly `VERIFY_TOKEN_TTL_HOURS` in the future. If the `users` row is missing entirely despite the UI showing success, check `storage/logs/app-*.log` for a logged exception first — `AuthService::register()` doesn't swallow database errors silently.

### Resetting local test data

```sql
-- Remove one test account and everything that references it
DELETE FROM auth_tokens WHERE user_id = (SELECT id FROM users WHERE email = 'test@example.com');
DELETE FROM saved_events WHERE user_id = (SELECT id FROM users WHERE email = 'test@example.com');
DELETE FROM users WHERE email = 'test@example.com';
```
Or, for a full reset, drop and recreate the database and re-run `php bin/migrate.php`.

## Project Structure

```
app/
  Controllers/        Public/, Auth/, Dashboard/, Admin/, Organizer/, Api/
  Core/                Router, Container (DI), Application, Request, Response, View, ErrorHandler, Database/Connection
  Middleware/          Csrf, Auth, Admin, SecurityHeaders
  Repositories/        Data-access layer (EventRepository, UserRepository, CategoryRepository, CityRepository, DistrictRepository)
  Services/
    Auth/              Registration, login, password reset (AuthService)
    Audit/              AuditLogger — audit_logs + moderation_actions
    Events/             UserEventService (post/edit/cancel/delete + validation), EventImageUploadService, DuplicateDetectionService,
                        EventLifecycleService (expiry/freshness), CalendarService (.ics), RegistrationRedirectService (SSRF-guarded redirect),
                        EventImageService (image fallback resolution), EventQualityService (discovery validation gate)
    Moderation/         ModerationService — admin actions, reports, duplicate merges
    Notifications/      NotificationService — email queue, digests, reminders, event-change + owner emails
    Security/           UrlValidator — http(s)/public-host check for user-entered URLs
    Location/           DistrictService — canonical district list + location/alias normalization
    Mail/               MailService — the only class that talks to SMTP (PHPMailer)
    Ingestion/          IngestionService (2-stage pipeline), web adapters (JSON-LD, Sitemap, RSS)
      Social/           InstagramSourceAdapter, FacebookSourceAdapter, XSourceAdapter, SocialPostExtractor
      Search/           SearchDiscoveryProviderInterface, NullSearchProvider, SearchQueryBuilder, SearchDiscoveryAdapter
  routes.php           All application routes
bin/
  migrate.php          Create DB + run migrations + seeds
  create-admin.php     Interactive super-admin account creator
  create-demo-user.php Creates or resets the demo user (demo@nevents.local / DemoPass123); not in production
  backfill-locations.php Gives older out-of-Tamil-Nadu events their district (--dry-run to preview)
  scheduler.php        Background scheduler: discovery, candidate processing, expiry, digests, reminders, email delivery
  worker.php           Background job queue worker (see below)
  test-platform.php    End-to-end acceptance tests (see Testing)
config/
  app.php              App/session/feature-flag/upload config
  database.php         PDO connection config
database/
  migrations/          Schema (idempotent SQL)
  seeds/                Base data + demo content (idempotent SQL)
public/
  index.php            Front controller / entry point
  css/, js/, images/    Static assets
  .htaccess             Apache rewrite rules + sensitive-file denial
resources/
  views/               PHP templates (layouts/, home/, events/, dashboard/, admin/, organizer-portal/, auth/, pages/, partials/, emails/)
public/
  images/event-fallbacks/  On-brand SVG fallback artwork per event category (see Event Images)
router.php              PHP built-in server router (see Running the App)
tests/
  fixtures/             API-shaped Instagram / Facebook / X responses (regenerated by bin/test-platform.php with current dates)
  Unit/, Integration/   Reserved for PHPUnit tests
public/uploads/events/  User-uploaded posters (random names; .htaccess disables script execution)
```

## Frontend Structure

- **Reusable event card**: `resources/views/partials/event-card.php` is the single card component used everywhere an event appears as a card — homepage, `/discover`, search results, district/category listings, dashboard, saved events, organizer pages, and the "Similar Events" section on the detail page. It expects `$event` (a repository row), `$is_saved` (bool) and optional `$show_distance` in its own local scope; every including view sets those and then does a bare `include`. **If a view needs to keep using a "primary" event/date/save-state variable *after* including this partial in a loop** (as `events/show.php` does for its sidebar), wrap that `include` in an IIFE (`(function () use (...) { include ...; })();`) — a bare `include` shares the includer's variable scope, so the partial's own internal locals (`$e`, `$dateStr`, `$timeStr`, `$isSaved`, `$image`, `$appUrl`) will silently overwrite any same-named variable in the parent view once the loop runs. This was a real, fixed bug (see the Troubleshooting entry below) and is the one sharp edge of this pattern — every other view that includes the card only loops event cards and never reads an event-scoped variable afterward, so it isn't a risk there.
- **Shared filter fields**: `resources/views/partials/event-filter-fields.php` holds the District/Category/Format/Price/Date filter controls, included by both the desktop sidebar form and the mobile offcanvas form on `/discover` — so filtering behaves identically at every breakpoint instead of the mobile drawer being a stripped-down subset.
- **Styling**: brand tokens live in `public/css/theme.css` (see [N Events Branding](#n-events-branding)); `public/css/nevents.css` holds the components and reads those tokens (its legacy `--ne-*` names are aliases), layered on top of Bootstrap 5's grid/utilities — there is no separate spacing-token scale (`--space-*`); spacing is expressed directly in `rem` on each rule. Card-context-only styles (e.g. the small circular save button used inside a thumbnail) are deliberately scoped with a descendant selector (`.event-card-img .event-card-save`, not a bare `.event-card-save`) precisely so the same class can be reused for a differently-laid-out control elsewhere (the full-width "Save Event" button in the detail-page sidebar) without inheriting positioning that only makes sense inside a thumbnail.
- **Responsive breakpoints exercised during development**: 360/390/768/1024/1440px, using Bootstrap's `sm`/`lg`/`xl` grid classes (`col-sm-6 col-xl-4` for card grids, `col-lg-8`/`col-lg-4` for the detail page's content/sidebar split, `d-lg-none`/`d-none d-lg-block` for the mobile-offcanvas-vs-desktop-sidebar filter switch).

## Background Jobs

Nothing slow happens during a page request: pages only read MySQL. Discovery, candidate processing, expiry and **all email sending** run in the background.

### Scheduler (`bin/scheduler.php`) — run every 5 minutes

```bash
php bin/scheduler.php
```

Runs every task that is due (each task remembers its last run in `system_settings`; a file lock prevents overlapping runs). Useful variants:

```bash
php bin/scheduler.php --list
```

```bash
php bin/scheduler.php discover-web process-candidates
```

| Task | Every | What it does |
|---|---|---|
| `discover-web` | 5 min | Runs each enabled web / sitemap / RSS / search source whose `refresh_interval` (minutes) has elapsed |
| `discover-social` | 5 min | Same for enabled Instagram / Facebook / X sources (only if configured) |
| `process-candidates` | 5 min | Pending raw candidates → validation → district → duplicates → publish or review |
| `refresh-sources` | 60 min | Freshness: references not re-seen at their source become `stale` → `missing` → event `outdated` (never deleted after one failure) |
| `expire-events` | 30 min | Events whose last occurrence ended → `completed` |
| `queue-daily-digest` | 15 min | Queues daily digests once each user's `digest_time` (IST) has passed |
| `queue-weekly-digest` | 15 min | Queues weekly digests on `digest_day` (default Monday) |
| `queue-reminders` | 10 min | Saved-event reminders: happening tomorrow, starting soon, registration closing |
| `send-emails` | 1 min | Delivers queued `notification_jobs` via SMTP (retry with backoff, max 3 tries) |
| `purge-social-raw` | daily | Erases stored social post text after `SOCIAL_RAW_RETENTION_DAYS` |

**Windows (XAMPP) — Task Scheduler**, every 5 minutes. **Already registered on this machine** as `NEvents Scheduler` (runs as the logged-in user, "interactive only" — it runs while you're signed in; XAMPP's MySQL must be running). `php-win.exe` runs it without a console window and `--log` writes each run to `storage/logs/scheduler-YYYY-MM-DD.log`:
```bash
schtasks /Create /SC MINUTE /MO 5 /TN "NEvents Scheduler" /TR "\"C:\xampp\php\php-win.exe\" \"C:\xampp\htdocs\Lordminds\N_Events\bin\scheduler.php\" --log" /F
```
Check / pause / remove it:
```bash
schtasks /Query /TN "NEvents Scheduler" /V /FO LIST
```
```bash
schtasks /Change /TN "NEvents Scheduler" /DISABLE
```
```bash
schtasks /Delete /TN "NEvents Scheduler" /F
```
**Linux cron:**
```bash
*/5 * * * * php /path/to/N_Events/bin/scheduler.php >> /path/to/N_Events/storage/logs/scheduler.log 2>&1
```

### Queue worker (`bin/worker.php`) — optional

`bin/worker.php` polls the `jobs` table for ad-hoc jobs (`send_notification`/`send_email` → delivers queued email, `ingest_source` → runs one source end-to-end, `verify_event`) with retry/backoff. The scheduler already covers everything recurring, so the worker is only needed if you enqueue ad-hoc jobs:

```bash
php bin/worker.php
```

No WhatsApp jobs exist.

## WhatsApp Number

- **Collected at signup and required.** The register form asks for Name, Email, WhatsApp/mobile number, Password, Confirm password and a required Terms/Privacy agreement (checked server-side too).
- **Stored once**, in `users.whatsapp_number`, normalized to E.164: Indian mobiles may be typed as `98765 43210`, `098765 43210`, `919876543210` or `+91 98765 43210` → `+919876543210` (must start 6–9). Other countries must be entered with `+` and country code (8–15 digits), e.g. `+44 20 7946 0958` → `+442079460958`. `users.phone` is no longer written (migration `003` moved any existing value into `whatsapp_number` and removed the identical duplicate copy).
- **The application does NOT** send WhatsApp messages, receive WhatsApp messages, use the WhatsApp Business API, WhatsApp templates, webhooks or delivery logs, or schedule WhatsApp reminders. **No WhatsApp credentials are required** to run it. The old `WHATSAPP_*` variables were removed from `.env.example`; `notification_preferences.whatsapp_enabled` and the `'whatsapp'` enum values on the notification tables are left in the schema only for backward compatibility and are never read or written.

Signup flow: enter details → server validation → user stored in MySQL → verification email queued/sent → user verifies (per `REQUIRE_EMAIL_VERIFICATION`) → logs in. No WhatsApp message is generated anywhere in that flow.

## User Event Posting

### Who and where
- **Only logged-in users** can post. `GET /events/create` behind `AuthMiddleware`; a visitor is redirected to `/login` and returned to `/events/create` after signing in.
- Navigation for logged-in users: **Post Event** button + account menu (Dashboard, **My Events**, Saved Events, Profile, Email Preferences). The old `/submit-event` and `/organizer-portal/create-event` URLs redirect to `/events/create`.

| Route | Purpose |
|---|---|
| `GET/POST /events/create` | Post an Event form (6 sections + live card preview) |
| `GET /my-events` | My Events — tabs: Upcoming, Under review, Drafts, Completed, Cancelled, Rejected/flagged; views, registration clicks, saves per event |
| `GET/POST /my-events/{id}/edit` | Edit (owner, or admin/moderator) |
| `POST /my-events/{id}/cancel` | Cancel (status `cancelled`, savers emailed) |
| `POST /my-events/{id}/delete` | Hard delete — only for events that were **never published** |
| `GET /my-events/{id}/submitted` | Success page: "Your event has been published." → View Event / My Events / Post Another Event |

### Form fields
Required (*): **Basic** — Title*, Short summary*, Detailed description* (plain text), Primary category*, up to 3 additional categories, tags, language, Format* (Offline/Online/Hybrid). **Date & time** — Start date*, Start time*, End date*, End time*, Timezone* (default `Asia/Kolkata`). **Location** (Offline/Hybrid) — District* (anywhere in India, picked with the location picker), City/Area*, Venue*, Address*, PIN code (6 digits, not starting with 0), latitude/longitude (inside India), Google Maps URL; (Online/Hybrid) platform name, public information URL. **Registration** — required yes/no, registration URL, deadline; ticket type Free/Paid/Donation/Not sure, currency (INR default), min/max price. **Organizer** — name*, email, phone, website, social profile URL, or "post as my organizer profile" if the user belongs to one. **Image** — optional poster.

### Storage & ownership
User events are rows in the **same canonical `events` table** as discovered events (no separate table): `events.created_by_user_id` (FK → users), `data_origin` (`user_submitted`; `organizer_submitted` when posted as an organizer profile; `admin_created` when posted by staff), `status`, `moderation_status` (`clean`/`flagged`/`needs_review`/`suspended`/`rejected`), `moderation_reason`, `published_at`, organizer contact columns (`organizer_display_name`, `organizer_email`, …), plus a `venues` row (with `locality` = City/Area), one `event_occurrences` row (start/end stored in UTC), `event_categories`, `event_tags` and `event_images`. The district comes from the chosen district's matching city (or its main city) → `events.city_id`, exactly like every other event.

Every edit/cancel/delete re-loads the event and checks `created_by_user_id === session user` server-side (moderators may manage any event). Hidden `user_id` fields are ignored — the form data never sets ownership.

### Publishing behaviour
A submission that passes every automatic check is **published immediately** (`status = published`) and appears in the normal lists. Checks: authenticated + not posting-suspended, rate limit, title/summary/description length, no HTML tags, valid category, valid format, dates valid (`end ≥ start`, not already ended, ≤ 2 years ahead, ≤ 60 days long), valid district for offline/hybrid, http(s) public URLs only, safe image, required fields, and duplicate detection.

It is held as `status = pending`, `moderation_status = needs_review` **only on concrete risk signals**: a possible duplicate (score 60–84, or the user confirmed "this is a different event" after an 85+ match), spam-pattern wording (betting, instant loans…), more than 3 links in the description, an all-caps title, repeated characters, or a submitter who already has ≥ 2 rejected/suspended events. The user sees why on the success page and gets an email.

### Editing, cancelling, deleting
- Edits update the canonical row (`updated_at`), re-run validation, re-run duplicate detection when title/date/district changed, and — if date/time/venue/district/format/registration link changed on a published event — cancel queued reminders (they're re-queued with the new time) and email everyone who saved it (`event_updated`). Minor text edits send nothing.
- An owner edit never re-publishes an event a moderator unpublished, rejected or suspended, and never clears a flag.
- **Cancel** sets `status = cancelled` (+ occurrences), removes it from upcoming lists, emails savers. **Delete** is only allowed for events that were never published — published events keep their record.

### Security
CSRF on every POST; PDO prepared statements everywhere; plain-text descriptions with all HTML tags rejected and every output escaped (`View::e`, `nl2br(View::e())`); JSON-LD and inline-JS values encoded with `JSON_HEX_*`; image uploads validated by magic bytes (`finfo`) + `getimagesize` type match + size/dimension limits, stored under a random name with an extension derived from the sniffed type (path traversal impossible) in `public/uploads/events/` whose `.htaccess` disables script execution; URLs must be http(s) to a public host (`UrlValidator`) and are re-checked with DNS/private-range SSRF rules at redirect time; rate limits; mass assignment impossible (explicit field whitelist); audit log entries for create/edit/cancel/delete.

### Duplicate detection
`DuplicateDetectionService` scores same-day candidates (IST) on title similarity (token overlap / containment, up to 50), district (15), start time within 15 minutes (20), venue (up to 10), registration URL (25), organizer (10) and source external ID (100). ≥ 85: the form stops and links to the existing event ("save that one instead"), with an explicit "this is a different event" override that sends it to review; 60–84: saved but held for review and queued in `/admin/events/duplicates`. User events and discovered events are matched against each other in both directions.

## Notifications (Email & Google Calendar)

Channels: **email** and **Google Calendar** only.

- **Email configuration** — the `MAIL_*` variables (see [Email Verification & Login Policy](#email-verification--login-policy)). `MailService` is the only class that talks to SMTP.
- **What's sent** — verification + password reset (immediate, transactional); everything else is **queued** in `notification_jobs` and delivered by `php bin/scheduler.php` (`send-emails`): daily digest, weekly digest, saved-event "happening tomorrow" and "starting soon" reminders, "registration closing" (within 24h of the deadline), event updated / postponed / cancelled (to users who saved it), and submission status for posters (published / held for review / unpublished / rejected).
- **Duplicates impossible** — each job has a unique fingerprint (e.g. user + event + occurrence start), so re-running the scheduler never double-sends; reminders are fingerprinted on the start time, so a rescheduled event gets new ones and the old queued ones are cancelled.
- **User preferences** (`/account/preferences`): email notifications on/off, daily digest, weekly digest, reminder the day before, reminder shortly before, event update notifications, preferred digest time (IST). No WhatsApp settings.
- **Google Calendar (Level 1, implemented)** — every event page has "Add to Google Calendar": a pre-filled `calendar.google.com/calendar/render?action=TEMPLATE` link with title, start/end in UTC, location (venue, address, locality, district — or "Online"), description (summary, description excerpt, registration link, event page link) and `ctz`. A `.ics` download (`/event/{slug}/calendar.ics`) is also available. *(Fixed in this release: both previously converted UTC times as if they were IST, shifting events by 5h30.)*
- **Google Calendar OAuth (Level 2)** — **not implemented**. The `calendar_connections` / `calendar_event_mappings` tables exist for it, but there is no OAuth flow; `GOOGLE_*` variables are unused.

## Moderation & Reports

- **Report Event** (logged-in users, event page): Fake event, Spam, Wrong information, Event is cancelled, Incorrect date/time, Incorrect venue, Broken registration link, Duplicate, Inappropriate content, Scam. One open report per user per event, `REPORT_MAX_PER_DAY` per user. 3 reports from different users auto-flag the event (it stays visible until reviewed).
- **Admin** (`/admin/events`): views for All / User-Submitted / Externally Discovered / Socially Discovered / Flagged & Needs Review / Reported; filters by status, district and source (`user_submitted`, `web_discovered`, `instagram`, `facebook`, `x`, `organizer_website`, `event_platform`, `partner_feed`, `admin_created`, …); columns Event, Submitter, Email, District, Category, Date, Created, Origin, Status, Reports. Each event page offers Publish / Unpublish / Flag / Clear flag / Reject / Suspend / Cancel with a reason (emailed to the submitter), "Suspend submitter from posting" (`users.can_post_events`), source references, reports and the audit history.
- `/admin/reports` — resolve/dismiss reports. `/admin/events/duplicates` — merge a pair (keeps one event, moves source references/saves/reports onto it, archives the other — a submitter's record is preserved) or mark "not a duplicate".
- Every moderation action writes `moderation_actions` + `audit_logs`.

## Event Registration Redirect

Every "Register Now" control anywhere in the app — event cards (homepage, `/discover`, search results, district/category listings, dashboard, saved events, related-events on the detail page) and the event detail page's own sidebar CTA — points to the identical internal route: `/event/{slug}/register`. No template ever embeds an external URL directly; the real destination is always looked up server-side.

**Where the URL is stored**: `events.registration_url` (a single column on the `events` table — organizer-provided registration links only). It's populated at creation (admin/organizer submission) or during ingestion (adapter-extracted `registration_url` / JSON-LD `offers.url`).

**The flow** (`app/Controllers/Public/EventController.php::registerRedirect()`):
1. Look up the event by slug server-side — the route never trusts a client-supplied destination (no open-redirect vector).
2. `RegistrationRedirectService::determineState($event, $occurrences)` classifies the event as `open` / `cancelled` / `completed` / `no_link` — the **same** method the event detail page calls to decide what to render, so the detail page and the redirect route can never disagree about an event's state.
3. `cancelled` / `completed` → a dedicated `events/registration-closed.php` page (distinct icon/copy per state) instead of any redirect or button.
4. `no_link` (empty `registration_url`) → `events/no-registration.php` — a professional "No Online Registration" message ("This event does not have an online registration link. Contact the organizer directly to register."). There is no fallback redirect to any other URL and no fabricated link; a missing link is shown honestly as missing.
5. `open` → `RegistrationRedirectService::isSafeExternalUrl()` re-validates the URL (blocks localhost/private IP ranges and non-http(s) schemes) before rendering `events/register-redirect.php`: a 5-second auto-redirect via `window.location.href` (same tab — a `setInterval` callback isn't a user gesture, so `window.open()` there would just get blocked by the browser's popup blocker) plus a manual "Go to Registration Now" button (`target="_blank" rel="noopener noreferrer"`, safe here because it's a real click).
6. Click analytics (`recordInteraction()` + `incrementClickCount()`) run inside a `try/catch` that can never block the redirect — an analytics failure degrades silently and never breaks registration.

**Known data-quality note**: all 5 seeded demo events currently carry `registration_url` values on the placeholder `https://example.com/events/{slug}` domain (each event still points to its own distinct path). That's intentional — a real, resolvable domain was needed to exercise the full redirect/analytics/SSRF-check path end-to-end, and per this project's data-integrity rules nothing here invents a realistic-looking fake URL to hide missing data. Every non-demo event needs a genuine `registration_url` before "Register Now" is useful to a real visitor; `no-registration.php` is the honest fallback for when it doesn't have one yet.

## N Events Branding

**Premium cream & brown** design language (2026-09-24): warm ivory surfaces, espresso accents, a muted-gold highlight and serif display headings. The logo artwork is unchanged in shape and recolored **Brown `#4B2E1E`** on Cream.

### Palette

| Role | Token | Value | Use |
|---|---|---|---|
| Espresso | `--palette-espresso` | `#2A1B12` | Headings, hero, footer, dark feature strips |
| Brown | `--palette-brown` / `--brand-primary` | `#4B2E1E` | Primary buttons (Ivory text 11.6 : 1), links, logo |
| Mocha | `--palette-mocha` / `--text-muted` | `#7A5238` | Muted text (6.4 : 1 on Ivory, 5.0 on Sand), button hover |
| Caramel | `--accent-caramel` / `--brand-accent` | `#B08256` | Accent lines, icons, saved heart (graphics only on light) |
| Gold | `--accent-gold` | `#C9A36A` | Highlights on dark (7.1 : 1), Free / Featured chips with Espresso text |
| Sand · Cream · Ivory | `--palette-sand` · `--palette-cream` · `--palette-ivory` | `#E9DCC8` · `#F6EFE4` · `#FBF8F2` | Surfaces; cards are warm white `#FFFDF9` |
| Text | `--text-primary` / `-secondary` / `-muted` | `#2A1B12` / `#5C4130` / `#7A5238` | All ≥ 4.5 : 1 on every light surface |

**Category colors** (`database/migrations/009_premium_category_colors.sql`) are five brown-family tones, each ≥ 5.2 : 1 as label text even on Sand: Brown `#4B2E1E`, Bronze `#7A4E2A`, Walnut `#6B4A2E`, Maroon `#6E3A2C`, Olive-brown `#5E4630`. Placeholder artwork (`public/images/event-fallbacks/`) and the email templates use the same tones.

**Typography:**
- Fraunces (serif) for large headings: hero, page, section, event and profile titles.
- Inter for all UI and body text.
- Montserrat for the brand name next to the logo mark.

### Section layout

Mostly light, with dark accents:

| Area | Pattern |
|---|---|
| Home | **Espresso hero**, then Ivory / Sand sections alternating (`$band()` in `home/index.php` skips empty sections). |
| Other pages | Light **Sand header** (`partials/page-header.php`, or the page's own header marked `band-sand`), Ivory content |
| Sign-in, registration, notices, onboarding | One Espresso screen holding an Ivory card |
| Admin / organizer portal | Slim Espresso header band, Ivory work area |
| Navbar / tab bar / footer | Frosted Ivory / Ivory with a gold active marker / Espresso with gold headings |

The band classes live in `theme.css`:
- `.band-cream` (Ivory) and `.band-sand`: the light schemes.
- `.band-dark` and `.band-hero` (Espresso): they re-point the tokens to Cream text with Gold links and buttons.
- Cards, badges, chips, forms, tables, dialogs, the search bar and the auth panels keep the light scheme on any band.

**Hero** (`home/index.php`):
- An Espresso gradient with a warm gold glow and a fine grain texture.
- The serif headline, with *find you.* in gold italic.
- An Ivory search bar, glass date chips and serif gold stats.
- A fanned, gently floating stack of **real upcoming event posters** (from the featured events). On tablets and phones it becomes a swipeable strip, and motion is off for people who prefer reduced motion.

Checked with a contrast audit of every text element on every public, signed-in and admin page at 360–1440px: 0 failures, no horizontal scroll, and no tap target under 24 px.

### Logo assets

| File | Use |
|---|---|
| `resources/brand/n-events-logo-master.webp` | **Official master file** as supplied (2000×2000, original teal on cream). Kept outside `public/`; only the asset generator reads it |
| `public/images/brand/n-events-logo.png` | Full logo (N + pin + EVENTS wordmark), Brown `#4B2E1E`, transparent, 495×512: footer, auth brand panel, emails |
| `public/images/brand/n-events-mark.png` | Symbol only (N + pin), Brown, transparent, 266×256: navbar / mobile header, beside the name "N Events" set in Montserrat |
| `public/images/brand/favicon-32.png`, `favicon-192.png`, `apple-touch-icon.png` | Browser tab / Android / iOS icons (192 and 180 on Cream, because iOS ignores transparency) |

`bin/build-brand-assets.ps1` crops the master and turns it into an alpha mask: alpha is the distance from the flat background, so anti-aliased edges are preserved. It then fills the mask with the brand colors. The artwork is never redrawn.

To regenerate, e.g. after replacing the master (`-Source path\to\file.png` for another file):
```bash
powershell -ExecutionPolicy Bypass -File bin\build-brand-assets.ps1 -Ink 4B2E1E -Background F6EFE4
```
If the brand colors change, update the tokens in `public/css/theme.css`. Also update three places that can't use CSS variables: the literal caret color in `nevents.css`'s `.ne-city-select` data-URI, the email templates in `resources/views/emails/`, and `<meta name="theme-color">` in `layouts/main.php`.

**Where the logo appears:** one partial renders it everywhere: `resources/views/partials/brand-logo.php`, called as `View::partial('brand-logo', [...])`. Its options are `logo_variant` (`lockup`|`full`), `logo_height`, `logo_tile` (a Cream tile on Brown backgrounds) and `logo_href`.

### Design system files

- **`public/css/theme.css`**: the only place colors, fonts, radii, shadows and focus rings are defined. It holds the palette and role tokens, `-rgb` channel variables for translucent tints, the band scopes, and Bootstrap 5 variable overrides, so no default Bootstrap blue or pink appears. Loaded before `nevents.css`.
- **`public/css/nevents.css`**: components (navbar, buttons incl. hover/active/focus/disabled/`.is-loading`, cards, badges, forms, auth split layout, footer, alerts/toasts, empty states, admin). Colors come from tokens only; the old `--ne-*` names still work as aliases, so the ~900 inline `style=""` references in views follow the theme.
- Fonts: Inter (UI) and Montserrat (the brand name, echoing the logo's geometric wordmark).
- Focus: a two-tone ring (a gap in the band's background, then Brown on light or Gold on dark), visible on every band.

## Mobile-First App Experience

N Events is designed phone-first and works as an **installable web app**, the base for Android/iOS apps later. The structure follows modern event platforms (Meetup-style discovery → event page → register) on the N Events brand.

### App shell (`layouts/main.php`)

| Piece | Behaviour |
|---|---|
| **Bottom tab bar** (< 992px) | Explore · Search · **+ Post** (raised, Espresso with a gold plus) · Saved · Profile ("Sign in" for guests). The active tab has a gold marker. It respects iOS safe areas (`viewport-fit=cover`, `env(safe-area-inset-*)`) and the page reserves space so content is never hidden. Not shown in admin |
| Top bar | Logo, search (tablet+), Join / Sign in. On phones the account icons are hidden, because the Profile tab covers them |
| Task screens | The event page (sticky **Register** bar) and the Post Event wizard (sticky **Back / Next**) replace the tab bar on phones, as apps do on detail and task screens (`$body_class` hook) |
| Installable | `public/manifest.webmanifest`: standalone display, Brown theme / Cream background, 192 / 512 / maskable-512 icons (`bin/build-brand-assets.ps1`), shortcuts (This weekend · Post an event · Saved). Served as `application/manifest+json` (`public/.htaccess`). There is no offline mode or service worker yet |
| Cache busting | `theme.css`, `nevents.css` and `nevents.js` carry `?v=<filemtime>`, so returning visitors always get the current code |

### Discovery

- **Home** (`home/index.php`) shows events first: a compact hero with search, then a category icon rail, then rows. Rows are *Upcoming in {district}*, *For you* (the signed-in user's interests), *Happening today*, *This weekend*, *Free*, *Online*. Each row (`partials/event-rail.php`) swipes sideways on phones and tablets (scroll-snap, the next card peeks) and becomes a 4-column grid on desktop. A row with no events is skipped, and the Cream/Brown band pattern stays intact.
- **Discover** (`events/discover.php`):
  - search, then the category rail, then **filter chips**: When (today / tomorrow / weekend / week / pick dates) · Type · Free · Location (search a place, or browse state → district) · Sort (recommended / soonest / newly added / most saved) · All filters · Clear;
  - each chip is a Bootstrap dropdown on desktop and a **bottom sheet** on phones;
  - every option is a plain link (`?when=weekend&free=1…`), so filtering works without JavaScript and inside app webviews;
  - pagination keeps all filters.
- **Event card** (`partials/event-card.php`) is one component everywhere:
  - a 16:9 image with price / Online / Featured chips and a **heart** (saved state shown, 40px target);
  - then date line, title (2 lines), "by organizer", place, category · "N saved";
  - the whole card is one link (a stretched title link, keyboard reachable);
  - a guest tapping the heart signs in and returns to the same page.
- Date filters are IST wall-clock values, converted to UTC in `EventRepository::buildWhere()`. The list and its count share that one filter builder.

### Event page (`events/show.php`)

- **Phones:** the image edge-to-edge first, then badges, title, "Hosted by" row and summary.
- **Info cards:**
  - **date**, with an *Add to calendar* sheet (Google · Apple · Outlook/.ics);
  - **venue**, with *Open in Maps*;
  - online details;
  - **price**, with the real registration deadline, highlighted within 48 hours.
- **Description** is rendered by `NEvents\Helpers\RichText`: HTML-escaped first, then a small Markdown subset (headings, lists, bold, http(s) links). A description that a source cut short gets "…" and a *Continue reading on {source}* link to the original page.
- **Sticky action bar on phones:** date/price · heart · share · **Register**. On desktop, a sticky side card. Share uses the native share sheet, otherwise copies the link.

### Profile & posting

- **Profile** (`dashboard/index.php`):
  - avatar, name, email, location picker, and Saved / My events / Interests counts;
  - app-style menu rows (Activity · Settings · More) and Sign out;
  - interest chips and event rows.
- **Interests** (`/account/interests`): grouped chips; they power *For you*.
- **Profile editing** (`/account`): name + WhatsApp number, using the same validation and E.164 normalization as signup. Email is fixed; password changes go through the reset flow.
- **Post Event** (`user-events/form.php`) on phones and tablets runs as a **step wizard**:
  - "2 of 6 · Date & Time" with a progress bar;
  - Next validates only that step's fields;
  - after a server-side error it opens on the step with the error;
  - the last step shows the card preview and **Publish**.
  - Desktop, and any device without JavaScript, gets the one-page form. Server-side validation is unchanged.

### Accessibility & QA (last run 2026-09-24)

- Contrast: every text element on every public, signed-in and admin page, at 360 / 390 / 768 / 1024 / 1440px: 0 failures (WCAG AA).
- No horizontal scrolling at any width.
- Touch targets: nothing under 24×24px (WCAG 2.2 AA) on phones. Chips and small buttons grow to 44px on touch screens (`@media (pointer: coarse)`); footer, breadcrumb and admin table links get taller tap rows.
- `php bin/test-platform.php`: 135 / 135.

### Not built yet

- Offline mode (service worker).
- Push notifications (email only today).
- Native app wrapping (e.g. Capacitor / Trusted Web Activity) — the manifest, safe areas and tab-bar shell are ready for it.
- Self-service account deletion: `/account/delete` is a stub with no link to it; the account page points to privacy@nevents.in.
- Attendee counts / RSVPs and groups, as Meetup has. N Events links to organizers' own registration, so it has no attendance data and shows "N saved" instead.

## Event Images

Every event card and the event detail hero always show an image — resolved centrally by `app/Services/Events/EventImageService.php` so no template repeats fallback logic:

1. **`events.featured_image_url`** if the event has one (covers both a source-provided photo and an organizer-uploaded poster — this schema uses one column for both).
2. Otherwise, **on-brand category artwork** from `public/images/event-fallbacks/{category-slug}.svg` — small (1–2 KB), on-brand SVGs generated from the event's primary category, using that category's real `color` from the `categories` table so the artwork always matches its badge color elsewhere in the UI. A subcategory without its own artwork (e.g. `machine-learning`) inherits its parent's (`artificial-intelligence`); anything uncovered falls back to `default.svg`. **Never** a broken image icon and never an external hotlink — every fallback asset is a local, project-owned file. Each fallback SVG's decorative icon badge sits in the artwork's bottom-right corner, deliberately clear of the top-left `VERIFIED`/`FEATURED` overlay badges and the bottom-left category label rendered on top of it.
3. Alt text is always event-specific — the event's own title for a real image, or `"{title} — {category} event artwork"` for a fallback — never a generic `"image"`/`"event"`.

**Card ↔ detail-page consistency**: both `resources/views/partials/event-card.php` and `resources/views/events/show.php` call `EventImageService::resolve($event)` on the same event row, so the primary image (and its alt text) shown on a card is guaranteed identical to the one shown on that event's detail page — there's no second, independently-maintained lookup that could drift.

**Lazy loading**: below-the-fold card images (`partials/event-card.php`) use `loading="lazy"`. The event detail page's hero image is always immediately visible on load, so it deliberately does **not** use `loading="lazy"` (uses `fetchpriority="high"` instead) — lazy-loading an above-the-fold LCP image only delays it.

Cards render at a fixed 16:9 ratio with `object-fit: cover`, so layout never shifts as images load. To add a fallback for a category not yet covered, drop a new SVG at `public/images/event-fallbacks/{slug}.svg` — no code change needed, `EventImageService` picks it up by slug automatically.

## Locations Across India

N Events covers all of India: **28 states and 8 union territories, 780 districts**, and for each district its main city plus other major places (about 1,000 in all). Puducherry has its four districts (Puducherry, Karaikal, Mahe, Yanam), the communes around Puducherry town (Ozhukarai, Villianur, Bahour, …) and the town's neighbourhoods (White Town, Lawspet, Muthialpet, …). Every selector reads from the same tables; there is no hardcoded list in any page.

### Where locations live

- **Tables:** `states` → `districts` (`state_id`, `name`, `slug`, `is_active`) → `cities` → `areas`. Tamil Nadu's 38 districts come from `002_expand_districts.sql` (unchanged); everything else from `database/migrations/010_india_locations.sql` (idempotent, safe to re-run).
- **Slugs are unique across India**, because district pages live at `/events/{slug}`. A district name used in two states carries its state: `bilaspur-chhattisgarh` / `bilaspur-himachal-pradesh`, `hamirpur-…`, `pratapgarh-…`.
- **Main city:** each district's first city (`sort_order` 0) is the city events are filed under by default (Mumbai City → Mumbai, Bengaluru Urban → Bengaluru, Kamrup Metropolitan → Guwahati). A place named in the address is used instead when it is one of the district's cities (Secunderabad, Navi Mumbai).
- **Canonical access:** `app/Services/Location/DistrictService.php` (`getStates()`, `getDistrictsByState()`, `activeDistrict()`, `searchPlaces()`, `resolveDistrictId()`); nothing queries the tables directly outside it and `DistrictRepository`.
- **Events still link through `cities`** (`events.city_id` → `cities.district_id` → `districts.state_id`). `EventRepository` filters take `district_id` or `state_id`.
- **District list** was compiled from each state's official list (2025). Recently announced changes may be missing or not yet reflected (Ladakh's five new districts, Delhi's reorganisation, Andhra Pradesh's proposed new districts), and Assam keeps Biswanath, Hojai, Bajali and Tamulpur as districts. Add or rename a district with a row in `districts` plus one in `cities` (its main city), or a later migration.

### Picking a location

- **Location picker** (`partials/location-picker.php` + `initLocationPickers()` in `nevents.js`): type a city, district, neighbourhood, old name or state and pick from the list (`GET /api/locations?q=`). Typing a state lists its districts. Used in the home search bar, the profile, onboarding and the post-an-event form. Accessible combobox with arrow keys, Enter and Escape.
- **Discover:** the Location chip has the same search, plus browsing *state → its districts* (`?state=` / `?district=`). The All filters sheet has State and District selects (`GET /api/districts?state=`).
- **All India** is the default for visitors: home and Discover show events from everywhere until a place is chosen.

### Alternate names and address matching

Source websites and people use other spellings and former names (`Bangalore`, `Bombay`, `Pondicherry`, `Trichy`, `Gurgaon`, `Allahabad`, `Port Blair`, …). These are in `district_aliases` (alias → district) and resolved by `DistrictService::resolveDistrictId($text, $context)`:

1. The whole text, then each comma-separated part, is matched **exactly** against district names and aliases; only if none match, against city and neighbourhood names (PIN codes and "district"/"dt." are ignored).
2. A state named anywhere in `$context` (the whole address) narrows the match, so `Bilaspur, Chhattisgarh` and `Dwarka, New Delhi` resolve correctly.
3. A name that could be **more than one district** and has no state to settle it (just "Bilaspur") is left unresolved and the event goes to review. There is no substring or "contains" matching: a confident exact match beats a plausible guess.

An alias must point to one district only. Add one with:
```sql
INSERT INTO district_aliases (alias, alias_norm, district_id)
VALUES ('SomeAlias', 'somealias', (SELECT id FROM districts WHERE slug = 'target-district-slug'));
```

Events discovered outside Tamil Nadu before this was added were kept with only a "City, State" label. `php bin/backfill-locations.php` (`--dry-run` to preview) gives them their district and city with the same matching; one it can't place (for example "Goa", which has two districts) is left as it was.

### Guest vs. logged-in selection

- **Guest (not logged in):** `POST /api/set-district` writes `$_SESSION['district_id']`, the session key every controller reads. `district_id=0` means All India.
- **Logged in:** the same call also saves the choice in `user_locations` (`UserRepository::setDistrictPreference()`, or `clearDistrictPreference()` for All India). On every page load, the signed-in preference comes first, then the visit's choice, then All India (`DistrictService::activeDistrict()`).

### Admin management

`/admin/districts` lists every district (filter by state) with a live published-event count and an Enable/Disable toggle (`districts.is_active`). Disabling one removes it from every selector immediately, without touching any event data. `/admin/events` can be filtered by district (districts that have events, with their state) and by `data_origin` (see next section).

## Event Discovery Sources

**This platform does not fabricate events, and discovery does not depend on Google.** Events come from user/organizer/admin posting and from a multi-source discovery engine. Every discovered item is a **candidate** first; nothing found by a crawler, feed, social API or search provider goes straight into the live list.

```
  Websites / feeds / sitemaps      Approved social APIs        Licensed search providers
            │                              │                              │
            └──────────────── SOURCE ADAPTERS (SourceAdapterInterface) ───┘
                                           │  discover → fetch → extract → normalize → validate
                                           ▼
                           raw_event_records  (CANDIDATES, processing_status = pending)
                                           │  IngestionService::processPendingRecords()
                                           ▼
     social filter (recap / job / ad / no date) → EventQualityService (title, future date,
     source URL, not cancelled) → duplicate detection across ALL events → district resolution
     → trust × confidence decision
                                           │
                     ┌─────────────────────┴───────────────────────┐
             auto-publish (trusted source,                  canonical event held as
             confident extraction, no flags)                pending / needs_review
                                           │
                                   events (+ event_sources)  →  same event lists as everything else
```

### Keyless discovery (no API keys)

The part of discovery that runs without any credentials, fully automatically on the scheduler:

```
 Public event websites ─┐
 RSS / Atom feeds ──────┼─► Web crawler ─► Event extractor ─► Duplicate detection ─► Database
 iCalendar (.ics) feeds ┘   (robots.txt,    (JSON-LD, micro-   (score vs every       (new event, or trust-
 Structured event pages      politeness,     data, ICS VEVENT)  event that day)       ranked update of the
                             same-site)                                               existing one)
```

**Crawler** (`web-crawler` source, `WebCrawlerAdapter`) — `config_json`:
```json
{ "start_urls": ["https://example.org/events"],
  "event_pattern": "#/events/[^/?]+$#",      "follow_pattern": "#[?&]page=\\d+#",
  "max_depth": 2, "max_pages": 40,           "allow_hosts": [],
  "strip_query": true,                       "region_scope": "india" }
```
- `strip_query` drops `?query` from event links. Platforms add tracking parameters (`?aff=`, `?eventOrigin=`) that would otherwise fetch the same page several times.
- `region_scope`:
  - Any source: an in-person event anywhere in India is filed under its district (see [Locations Across India](#locations-across-india)). A page that states another country is rejected (`outside_india`).
  - `"tamil_nadu"` (default): an event that isn't online and whose district can't be resolved is held for review.
  - `"india"` (national platforms): an event identifiably in India whose district still can't be resolved (for example only "Goa") is kept, labelled "City, State" (`venues.locality`, shown on cards and the event page). Anything not identifiably in India is rejected (`outside_india` / `location_not_identified_as_india`), and its raw record is kept for audit. A Tamil Nadu address whose district can't be resolved is still held for review.
- **Online discovered events** (any source) are kept only if they are **from India or Canada AND in English, Tamil, Hindi, Malayalam or Telugu** (`Web\OnlineAudience`, owner's rule 2026-09-24). If the country can't be determined, the event is rejected. Events people post themselves are not filtered.
  - **Language:** the Tamil / Devanagari / Malayalam / Telugu scripts are detected directly. Latin-script text counts as English only when common English words clearly outnumber French / Spanish / German / Portuguese ones.
  - **Country clues, strongest first:** stated country; Canadian or Indian place names in the text; INR / CAD price; named time zone (e.g. `Asia/Kolkata`, `America/Toronto`); a `+05:30` start offset; `.in` / `.ca` links.
  - Canada is never inferred from a UTC offset alone, because its offsets are shared with the USA.
  - Rejected candidates are recorded as `online_outside_audience: country_… / language_…`.
- Event URLs named only in a listing page's structured data (not as `<a>` links) are followed too.
- **Category**: `EventCategoryClassifier` picks the primary category from title keywords, then description, then the schema.org type (`MusicEvent` → Culture, `SportsEvent` → Fitness…), then Community. Topics win over formats ("AI workshop" → Artificial Intelligence). A category chosen by a person is never replaced.
It fetches the start pages, follows links matching `follow_pattern` (pagination, category pages) up to `max_depth`, queues links matching `event_pattern` as event pages, and extracts every schema.org `Event` (any subtype, inside `@graph`/`ItemList` too) as JSON-LD or microdata. It never leaves the start pages' hosts (plus `allow_hosts`) and stops at `max_pages` per run.

**Politeness and permission** — every request (crawler, feeds, pages) goes through `AbstractSourceAdapter::httpFetch()`:
- **robots.txt** is fetched once per host per run and obeyed (RFC 9309: the most specific `User-agent` group, longest-match `Allow`/`Disallow` with `*` and `$`). A disallowed page is skipped silently and counted as `robots_skipped`, not as an error. An unreachable/5xx robots.txt means *don't crawl*; a missing (404) one means *allowed*.
- **Per-host delay** of `DISCOVERY_MIN_DELAY_MS`, or the site's `Crawl-delay` if larger. The crawler identifies itself with `DISCOVERY_USER_AGENT`.
- Only public http(s) addresses, with SSRF checks on every redirect hop.
- robots.txt is not a licence. **Check each site's terms before enabling it** and set `sources.terms_status` accordingly.

**Updating existing events (trust-ranked merge, `EventMergeService`)** — when a candidate matches an existing event (same source item again with changed content, or another source describing the same event):
- Every event stores `content_score` = average of the source's `trust_level` and the extraction confidence of whoever last set its details (`content_source_id`).
- An incoming candidate with an **equal or higher** score updates the fields that differ: start/end time, venue/address, district, registration link, price, organizer, and the description if the new one is clearly longer. Provenance then moves to that source.
- A **lower** score (or a candidate carrying review flags such as a date without a time) only **fills empty fields**. A conflicting value is logged in `event_conflicts` and shown on the admin event page (**Source conflicts** card), where an admin edits the event if the reported value is right and marks the conflict **Resolved**, or **Dismisses** it. Conflicts are never applied automatically.
- **Events a person posted** (user / organizer / admin) are never overwritten. A differing date, venue or link is logged as a conflict and the event is flagged for moderators.
- A changed date, venue or link on a published event cancels queued reminders and emails everyone who saved it.
- A held discovered event is published once a trusted, confident source confirms it.

**Changes are noticed** because each candidate's fingerprint covers title, times, venue, city, link, price, format, organizer, description, image and status. A re-crawl that sees different content re-processes the item; identical content only updates `last_seen_at`.

**Try a site before enabling it** (dry run, nothing saved):
```bash
php bin/discovery-probe.php https://example.org/events --type=crawl --pattern="#/events/[^/?]+$#"
```
`--type=page|crawl|rss|ics|sitemap`, `--follow=REGEX`, `--max=N`. It prints every candidate found, how its district resolves, and the pages robots.txt blocked.

**Enable** — set the source's config and enable it:
```sql
UPDATE sources SET config_json = '{"start_urls":["https://example.org/events"],"event_pattern":"#/events/[^/?]+$#"}', enabled = 1 WHERE slug = 'web-crawler';
UPDATE sources SET config_json = '{"urls":["https://example.org/calendar.ics"]}', enabled = 1 WHERE slug = 'ics-feeds';
```
Or use `/admin/sources`. For several sites, add one source row per site, so each gets its own trust level and health status. The scheduler (`discover-web`, then `process-candidates`) runs each source every `refresh_interval` minutes.

### Public event platforms (migration 006)

Enabled on 2026-09-24 at the site owner's request. All four are `WebCrawlerAdapter` sources with `region_scope: "india"`, a 6-hour refresh and a per-host delay. **Their terms of service were not verified**, so `terms_status` is `unknown`. Review each platform's terms, and disable a source in `/admin/sources` if they don't permit this use.

| Source (slug) | Start pages | Trust | Events after the first runs (2026-09-24) |
|---|---|---|---|
| Meetup (`meetup-web`) | `/find/in--chennai/`, `/find/in--coimbatore/`, `/find/in--madurai/` + each linked event page | 75 | 23 published, 1 in review |
| Eventbrite (`eventbrite-web`) | `/d/india--chennai/events/` (pages 1–2), Coimbatore, Madurai, Salem. The listing has dates only, so every `/e/` page is fetched for exact times | 75 | 18 published, 4 in review (mostly date-only) |
| District (`district-web`) | `upcoming-events-in-chennai / coimbatore / puducherry` (from District's own sitemap) + each `-buy-tickets` page | 75 | 62 published, 2 in review, Dubai and unlocatable listings rejected |
| EventsFlare (`eventsflare-web`) | home, `/all-events`, `/online-events` | 70 | 1 published (Delhi), Bali rejected |
| Eventbrite online, Indian languages (`eventbrite-online-lang`, migration 008) | `/d/online/tamil/`, `/hindi/`, `/telugu/`, `/malayalam/` + each `/e/` page. Online events pass only if they're from India or Canada and in English / Tamil / Hindi / Malayalam / Telugu | 75 | First run: 6 published, 3 in review (date-only), 14 rejected as `country_unknown`. Eventbrite's `/d/india/online--events/` and `eventbrite.ca /d/canada/online--events/` were not added: they are keyword searches that mostly return in-person events |

Not added:
- **MeraEvents**: every page sits behind a Cloudflare bot challenge, which the crawler does not try to get past.
- **Awwwards `/websites/events/`**: returns 502 to crawlers, and it's a gallery of event *websites*, not events.
- **Wix `/event/website`**: a product page with no events. Individual organizer sites built with Wix Events can each be added as a crawler source.

To cover more cities, add start URLs to a source's `config_json`, e.g. `https://www.meetup.com/find/in--bengaluru/`.

### The `data_origin` field (internal only — never shown as a badge)

`discovered` (web or social discovery; the platform comes from `sources.platform`), `user_submitted`, `organizer_submitted`, `admin_created`, `partner_feed`, `demo`, `seed`. The demo seed events stay `demo` and can be hidden with `'exclude_demo' => true`.

### One event list for every origin

`EventRepository::findPublished()` (and the Today / Weekend / Featured / district / category / search variants) selects canonical events with `status = 'published'` and a scheduled occurrence starting in the future, filtered by district (`events.city_id → cities.district_id`), category, format, price and date — **regardless of origin**. There is no separate "user events" page: a published community event shows up on `/discover`, `/events/{district}`, category pages, search, the homepage and the dashboard exactly like a discovered one, using the same `partials/event-card.php`. Online events have no district, so they appear in online/all-district views, not under a specific district.

### Adapters

| Adapter | Source | Acquisition | Credentials | Status in this install |
|---|---|---|---|---|
| `WebCrawlerAdapter` | Public event websites (organizer, venue, college, community, ticketing listing pages) | crawl listing pages → event pages → schema.org Event JSON-LD / microdata | none — `config_json.start_urls` | Implemented, tested end-to-end against a local fixture site; source `web-crawler` **disabled** (no site configured) |
| `IcsFeedAdapter` | Public iCalendar feeds (organizer calendars, Google Calendar public `.ics`, Meetup group `.ics`) | `VEVENT`s | none — `config_json.url` or `urls` | Implemented, tested with a fixture feed; source `ics-feeds` **disabled** (no feed configured) |
| `JsonLdEventAdapter` | Individual organizer / event / conference / college pages | schema.org `Event` (and subtypes) JSON-LD **and microdata** | none — `config_json.url` or `urls` | Implemented, tested with a local page fixture; **disabled** (no URL configured) |
| `SitemapJsonLdAdapter` | Sites with an XML sitemap (+ sitemap index) | sitemap → pages matching `url_pattern` → JSON-LD | none — `config_json.url`, `url_pattern`, `max_urls` | Implemented; **disabled** (no URL configured); not run against a live site |
| `RssEventAdapter` | RSS/Atom event feeds | feed items → each item's page → structured data | none — `config_json.url` (`follow_item_links`, `max_items`) | Implemented, tested with a fixture feed; **disabled**. An item whose page has no Event data falls back to the feed entry, whose date is only a *publication* date, so that fallback always goes to review |
| `InstagramSourceAdapter` | Organizer-authorized Instagram professional accounts; hashtags only with Meta approval | Instagram Graph API `/{ig-user-id}/media`, `/ig_hashtag_search` → `/{hashtag-id}/recent_media` | `INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_ACCOUNT_IDS` (or `config_json.accounts`), `META_GRAPH_API_VERSION` | **Not configured — disabled.** Tested only with `tests/fixtures/instagram_media.json` |
| `FacebookSourceAdapter` | Facebook Pages the app is authorized for | Graph API `/{page-id}/posts` | `FACEBOOK_PAGE_ACCESS_TOKEN`, `FACEBOOK_PAGE_IDS` | **Not configured — disabled.** Tested only with `tests/fixtures/facebook_page_posts.json` |
| `XSourceAdapter` | Public posts matching generated queries | X API v2 `GET /2/tweets/search/recent` | `X_BEARER_TOKEN` (tier with search) | **Not configured — disabled.** Tested only with `tests/fixtures/x_recent_search.json` |
| `SearchDiscoveryAdapter` | Licensed web search → candidate URLs → JSON-LD | `SearchDiscoveryProviderInterface` | a provider implementation + its key | **No provider bundled** (`NullSearchProvider`) — disabled |
| Manual / user posting | `/events/create` | form | — | Working |

Every adapter implements `discover()`, `fetch()`, `extract()`, `normalize()`, `validate()`, `healthCheck()`, `isConfigured()` and `getSourceMetadata()`. All HTTP goes through `AbstractSourceAdapter::httpGet()`: http(s) only, DNS + private/link-local/metadata range blocking, **redirects followed manually with every hop re-checked**, 5 MB cap, and API tokens never appear in logs or error messages.

### Social platforms — what is and isn't possible

Publicly visible in a browser ≠ available through an API. The connectors use **only official APIs with credentials the platform issued to this app**, and are disabled otherwise. They never log in with passwords, automate a browser, scrape platform HTML, solve CAPTCHAs, or read private accounts.

- **Instagram** — works for Instagram professional (business/creator) accounts that authorized your Meta app (organizer-authorized). Hashtag search needs Meta's app review for public content access and is limited to 30 unique hashtags per 7 days. There is no global Instagram post search.
- **Facebook** — Page posts for Pages your app holds a Page token for (or has approved public-content access to). The old public Facebook event search is not available and is not assumed.
- **X** — recent search covers only the last 7 days and needs a paid tier that includes search; monthly read caps apply. Queries come from `SearchQueryBuilder` (districts × keywords such as "AI meetup Chennai", "cycling event Erode"), rotated across runs and capped by `config_json.max_queries`.
- Future connectors (LinkedIn, YouTube, Reddit, Telegram channels, college portals, associations) plug in by implementing `SourceAdapterInterface` (or extending `AbstractSocialAdapter`) and registering the class in `Application::registerCoreBindings()`. **Registering a class never activates it** — a source row must be enabled by an admin, and the admin UI refuses to enable a connector whose credentials/config are missing.

**Social post → event candidate** (`SocialPostExtractor`):
- **Rejected outright:** thank-you/recap/highlights/throwback posts, job posts ("we're hiring"), product ads ("% off", "buy now"), news, posts saying the event is cancelled/postponed, posts with fewer than two independent event signals (registration/RSVP/tickets/"Venue:"/"Date:"/📅/📍/meetup/workshop…), posts without a findable date, and posts whose date is before the post itself.
- **Dates are never invented.** An explicit date with year and time is high confidence. A missing year (−20), missing time (−25), ambiguous numeric date like 05/06 (−15), relative wording like "this Saturday" (−45) or several different dates (−40) lower the confidence, and anything below `DISCOVERY_AUTO_PUBLISH_MIN_CONFIDENCE` goes to review.
- **District** comes from structured place data, an explicit "Venue:/Location:/📍" line resolved through `DistrictService` (exact district/alias/area/city — no substring guessing), or the source account's configured home district (`config_json.accounts[].district`, −10 confidence). Never from hashtags or random words.
- **Traceability** — `event_sources` keeps the platform (via `sources`), post URL (`source_url`), post ID (`external_id`, e.g. `instagram:1790…`), account (`account_handle`), first seen / last seen / last checked, confidence and registration URL.
- **Images** — a post's image is kept only if the source's `config_json.image_reuse_permitted` is `true`; otherwise the event uses category artwork. No fake posters are generated.
- **Retention** — only the fields needed for processing are stored; the post text is erased from processed candidates after `SOCIAL_RAW_RETENTION_DAYS`.

### Source registry, trust and priority

`sources` columns: `name`, `slug`, `platform`, `source_type`, `domain`, `acquisition_method`, `adapter_class`, `enabled`, `requires_auth`, `credentials_ref`, `rate_limit_rpm`, `refresh_interval` (minutes), `trust_level` (0–100), `terms_status`, `last_checked_at` (last run), `last_success_at`, `health_status`, `error_count`, `config_json`. Suggested trust levels reflect source priority: official organizer page / ticketing platform 80–90, structured JSON-LD site 75, trusted community platform 70, **organizer-authorized social account 70–75** (raise it per source once you've confirmed the account is the organizer's), public social post 45–55, general search candidate 35. Lower trust never rejects an event — it only means a moderator publishes it.

### Enabling a source

1. Pick a source you're permitted to read (terms, robots.txt / API terms).
2. Set its config, e.g. a sitemap:
   ```sql
   UPDATE sources SET config_json = '{"url":"https://example.org/sitemap.xml","url_pattern":"#/events?/#","max_urls":30}', trust_level = 80 WHERE slug = 'sitemap-jsonld';
   ```
   or an Instagram account the organizer authorized (token in `.env`):
   ```sql
   UPDATE sources SET config_json = '{"accounts":[{"id":"17841400000000000","handle":"chennai_ai_builders","district":"Chennai"}]}', trust_level = 75 WHERE slug = 'instagram-graph';
   ```
3. Enable it in `/admin/sources` (refused if credentials/config are missing), press **Run Now** to test, then let `php bin/scheduler.php` run it on its `refresh_interval`.

### Duplicates, freshness, cancellation, expiry

- **Duplicates across sources** — before creating anything, each candidate is scored against existing events on the same day (see [User Event Posting → Duplicate detection](#duplicate-detection)); ≥ 85 attaches it as another `event_sources` row of the existing canonical event (one card, all source references kept) and runs the trust-ranked merge above, 60–84 creates a held event + an admin duplicate pair. The same title in the same district at the same time is a merge even if the venue text differs, because the merge then decides which venue is right.
- **Freshness** — a source run that sees a known item again updates `last_seen_at`; `refresh-sources` marks references the source stopped returning as `stale`, then `missing` after 3 successful runs without them, then flags the event `verification_status = outdated`. One failed fetch never deletes or hides anything.
- **Cancelled at source** — if a source later reports `EventCancelled`/`EventPostponed`, the matching canonical event is set to `cancelled`/`postponed`, reminders are cancelled and savers are emailed (for a community member's own listing it's flagged for a moderator instead of overridden).
- **Expiry** — upcoming lists only show future occurrences; `expire-events` also marks finished events `completed`.

### Search providers

`SearchDiscoveryProviderInterface` (`name()`, `isConfigured()`, `search($query, $limit)`) is the extension point for a licensed web-search API. **No provider is bundled and Google result pages are never scraped.** With the default `NullSearchProvider` search discovery is simply off — direct source adapters, social APIs and user posting keep working. Search hits are only URLs: each page must carry schema.org Event data to become a candidate, and search candidates always go to review (trust 35).

## External Services

All optional — the app runs without any of them.

**Email (SMTP)** — set `MAIL_HOST`/`MAIL_PORT`/`MAIL_ENCRYPTION`/`MAIL_USERNAME`/`MAIL_PASSWORD`. Without valid credentials, verification/reset emails fail to send (registration itself still succeeds) and queued notifications retry 3 times then stay `failed` in `notification_jobs` — nothing is reported as sent. For local testing without SMTP, read the verification token from the `auth_tokens` table. *(Fixed in this release: `MailService` previously read `config('mail')`, which is always empty because `config/app.php` is namespaced under `app.` — so no email could ever be sent even with valid `MAIL_*` values. It now reads `app.mail`.)*

**Google Calendar** — "Add to Google Calendar" and `.ics` need no credentials. OAuth sync is not implemented (see [Notifications](#notifications-email--google-calendar)).

**WhatsApp** — not used. The number is collected at signup only; no WhatsApp provider, API or credentials exist in the app.

**Social / search APIs** — see [Event Discovery Sources](#event-discovery-sources) for the exact credentials each connector needs.

## Email Verification & Login Policy

### Setup

All outbound mail (verification, password reset) goes through one class, `app/Services/Mail/MailService.php`, using PHPMailer over SMTP — no other file talks to SMTP directly. Configure it with the `MAIL_*` variables in [Environment Variables](#environment-variables):

```
MAIL_PROVIDER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-address@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=noreply@nevents.in
MAIL_FROM_NAME="N Events"
```

**If you use Gmail SMTP:** you cannot use your normal Google account password — Google blocks it. Enable 2-Step Verification on the account, then generate a [16-character App Password](https://myaccount.google.com/apppasswords) and use that as `MAIL_PASSWORD`. Any other SMTP provider (SendGrid, Mailgun, Brevo, Amazon SES, your host's own SMTP) works the same way — just point `MAIL_HOST`/`MAIL_PORT`/`MAIL_ENCRYPTION`/`MAIL_USERNAME`/`MAIL_PASSWORD` at it. Never commit real values — `.env.example` only ever has placeholders.

`MailService` **never claims an email was sent unless PHPMailer actually accepted it for delivery.** If `MAIL_HOST`/`MAIL_USERNAME` are blank, or the SMTP handshake fails for any reason, `sendVerificationEmail()`/`sendPasswordResetEmail()` return `false`, the failure is logged (`verification_email_failed` / `password_reset_email_failed` in `storage/logs/app-*.log`, with the SMTP diagnostic message but never the password), and the caller shows an honest "we couldn't send that email" message with a way to retry — it never says "check your email" when nothing was sent.

### Flow

```
Register → user row created (unverified) → secure token generated (random_bytes,
SHA-256 hash stored — never the raw token) → MailService attempts delivery →
honest success/failure message shown
```

The verification link is `{APP_URL}/verify-email/{token}` — always built from `APP_URL`, never hardcoded to `localhost`. Tokens expire after `VERIFY_TOKEN_TTL_HOURS` (default 24h) and become unusable the moment they're used (`auth_tokens.used_at`). Clicking an already-used-but-still-recognizable link shows "Your email address has already been verified" rather than a generic error; a genuinely expired/invalid one links to Resend.

### Resend Verification

`GET/POST /resend-verification` (and a "Resend Verification Email" button on the verification-required screen). To prevent account enumeration, the response is **identical** whether the email doesn't exist, is already verified, or was just rate-limited to look at — it never reveals which case applied. Requesting again before `RESEND_VERIFICATION_COOLDOWN_SECONDS` (default 60s) has passed since the last token returns a "please wait" message instead of sending; a successful resend invalidates the previous token first, so only the newest link ever works.

### Verification Requirement (`REQUIRE_EMAIL_VERIFICATION`)

One config flag, checked in exactly one place (`AuthService::login()`), controls whether an unverified account can sign in:

- **`true` (default):** correct credentials + unverified email → the password is accepted, but no session is created. Instead of a generic "invalid password" error, the user is shown a dedicated **Email Verification Required** screen (`auth/verify-required.php`) with their email masked (`ka***@example.com`), a Resend button, and a way back to Sign In.
- **`false`:** the same account can sign in immediately, `email_verified_at` is left untouched (never artificially set) — verification is tracked but not enforced.

Wrong password, a suspended/deleted account, and an unverified account are three distinct, correctly-ordered checks (credentials → account status → verification) — none of them get conflated into the same error message.

## Troubleshooting

**"Database connection failed" on every page** — check `DB_HOST`/`DB_PORT`/`DB_USERNAME`/`DB_PASSWORD` in `.env`, confirm MySQL is running (XAMPP Control Panel), and that you've run `php bin/migrate.php` at least once.

**`/sitemap.xml` or `/robots.txt` return 404** — you're running the built-in PHP server without `router.php`. Use `php -S localhost:8080 -t public router.php` (see [Running the App](#running-the-app)).

**Every page 404s under Apache/XAMPP** — `AllowOverride All` isn't set for your htdocs path, or `mod_rewrite` isn't enabled; both are required for `public/.htaccess` to route requests to `index.php`.

**A branded "Something Went Wrong" page (or JSON error) instead of the feature working** — every uncaught exception and fatal error goes through `app/Core/ErrorHandler.php`, which logs the real error to `storage/logs/app-YYYY-MM-DD.log` and shows you the full message/file/line/trace on that page when `APP_DEBUG=true`, or a generic message when `APP_DEBUG=false`. Check the log (or the on-page trace in debug mode) for the actual file/line — it's very likely a genuine bug in the corresponding controller/repository, not a config issue.

**Login says "please verify your email" / shows the Verification Required screen** — this is expected when `REQUIRE_EMAIL_VERIFICATION=true` (default) and the account's `email_verified_at` is still `NULL`. Use "Resend Verification Email" once SMTP is configured, or set `REQUIRE_EMAIL_VERIFICATION=false` in `.env` to unblock login without touching the verification field, or (local testing only) `UPDATE users SET email_verified_at = NOW() WHERE email = '...';`.

**Verification email not received** — first check `storage/logs/app-*.log` for `verification_email_sent` vs `verification_email_failed` for that email. If you see `failed`, the log line names the reason (SMTP not configured, auth failure, connection refused, etc.) without ever logging the password. If you see `sent` but nothing arrived, check the destination's spam folder, confirm `MAIL_FROM_ADDRESS` is a domain your SMTP provider is actually allowed to send as, and confirm the provider's dashboard/activity log shows the send (a "sent" on our side just means PHPMailer's SMTP conversation completed — the receiving mailbox provider can still silently drop it).

**"SMTP Error: Could not authenticate" / auth failure in the log** — for Gmail, you're almost certainly using your normal account password instead of an [App Password](https://myaccount.google.com/apppasswords) (requires 2-Step Verification enabled first). For other providers, double-check `MAIL_USERNAME`/`MAIL_PASSWORD` and that `MAIL_PORT`/`MAIL_ENCRYPTION` match what the provider expects (587+tls or 465+ssl are the two common combinations).

**Verification link looks wrong / points to `localhost` in an email sent from a deployed server** — `APP_URL` in `.env` on that server isn't set to the real public URL. The link is always built as `{APP_URL}/verify-email/{token}` — fix `APP_URL`, not the email template.

**"Already verified" or "invalid/expired" when a link should work** — links are single-use (`auth_tokens.used_at`) and expire after `VERIFY_TOKEN_TTL_HOURS`. Requesting a resend invalidates the previous link, so clicking an older email after requesting a new one will correctly show it as no longer valid — this is expected, not a bug.

**Registration form reports success but the `users` row is missing** — check `storage/logs/app-*.log` for an uncaught exception first (a schema mismatch or bad query would surface there via the global error handler, not fail silently). Then confirm you're actually looking at the same database the app is using: print `DB_HOST`/`DB_DATABASE` from the *same* `.env` the running server loaded (multiple local MySQL installs / a stale copied `.env` are the usual cause of "it saved somewhere, just not where I'm looking").

**`composer install` fails / "composer: command not found"** — install Composer from https://getcomposer.org and ensure it's on your `PATH`.

**Admin panel shows nothing / redirects to login** — you need an account with the `super_admin`, `admin`, or `moderator` role; run `php bin/create-admin.php`.

**An event image looks broken or blank** — this shouldn't happen (see [Event Images](#event-images) — every event always resolves to either a real image or a local SVG fallback). If it does, check that `public/images/event-fallbacks/` wasn't deleted/excluded from deployment, and that the event's `featured_image_url` (if set) is a reachable `https://` URL — a dead source URL is a data problem, not a code problem, and won't fall back automatically since a non-empty URL is trusted as "has an image".

**An event's image looks right on its card but different on its detail page (or vice versa)** — shouldn't happen by construction (both call `EventImageService::resolve()` on the same event row, see [Event Images](#event-images)); if you do see a mismatch, the two rows are for genuinely different events (check the slug in the URL) rather than the same event resolving inconsistently.

**Fallback artwork path 404s / doesn't match what's in the browser vs. on disk** — fallback SVGs are served as plain static files at `public/images/event-fallbacks/{slug}.svg`, referenced by `EventImageService` as `{APP_URL}/images/event-fallbacks/{slug}.svg` (it used to be the root-relative `/images/…`, which broke under a sub-path install such as `http://localhost/Lordminds/N_Events/public`). Uploaded posters are stored as `uploads/events/YYYY/MM/<random>.ext` and also resolved against `APP_URL`. If a category's artwork 404s, confirm the file exists under `public/images/event-fallbacks/` with exactly that category's slug — there's no database row or upload involved, so a missing file is purely a missing/misnamed asset on disk.

**"Register Now" goes to a different event than the one on the page** — this was a real, now-fixed bug: `events/show.php` renders a "Similar Events" card loop above its own registration sidebar, and because a bare PHP `include` shares the includer's variable scope, `partials/event-card.php`'s internal `$e`/`$dateStr`/`$timeStr`/`$isSaved` locals were overwriting the *page's own* same-named variables — so the sidebar's Register Now link, its "Event Details" date/venue, and its Save-button state could all silently show the *last related event's* data instead of the current page's, whenever an event had at least one related event. Fixed by isolating that `include` inside a closure (see [Frontend Structure](#frontend-structure)). If you see this again anywhere, it means a new `include __DIR__ . '/../partials/event-card.php'` was added inside a loop in a view that also uses `$e`/`$dateStr`/`$timeStr`/`$isSaved` for something else afterward — wrap it the same way.

**"Register Now" button does nothing / isn't shown on a card** — a card only shows a "Register Now" button when `registration_url` is non-empty; if it's empty, the card correctly shows "View Details" instead (there is no dead/disabled Register button). On the detail page itself, an event with no `registration_url` always shows the "No Online Registration" page rather than a non-functional button — see [Event Registration Redirect](#event-registration-redirect).

**PHP "headers already sent" warning** — almost always caused by whitespace or output *before* an opening `<?php` tag (or after a closing `?>`) in a file that later needs to set a header/cookie/session, or by `echo`/`print` executing before a redirect (`header('Location: ...')`) is issued. Check the file/line the warning names first; the fix is to remove the stray output, not to suppress the warning.

**Cards misaligned / a badge's text is clipped** — event cards use `.event-card` with `display:flex; flex-direction:column; height:100%` inside a Bootstrap grid `row`, so uneven card heights within the same row are a genuine bug (check the parent uses `row` + `col-*` with the `h-100` class reaching the card, not a plain unconstrained flex/grid wrapper). Badge text (`VERIFIED`/`FEATURED`/etc.) getting clipped mid-word rather than wrapping onto its own line was a real, now-fixed bug — `.event-card-badges` was only constrained on its `left` edge, so in a narrower card (e.g. a 4-column grid) the badge row could overflow the card's right edge without wrapping; it now has an explicit `right` offset too so wrapping is always measured against the card's real width.

**Horizontal scroll / overflow on mobile** — the page body itself should never scroll horizontally at 360–768px; if something does overflow, it's almost always a fixed pixel `width` (instead of `max-width: 100%`) on an image, table, or an inline `style="width:...px"` left over from a desktop-only tweak — search the specific component's inline styles first, since this codebase mostly styles via inline `style=""` attributes rather than a separate stylesheet per component.

**Mobile filter drawer doesn't show District/Category filters** — it should; `/discover`'s mobile offcanvas includes the same `partials/event-filter-fields.php` as the desktop sidebar (see [Frontend Structure](#frontend-structure)). If it's showing a reduced set again, check the offcanvas body in `resources/views/events/discover.php` still includes that partial rather than a hand-written subset.

**A place can't be found in the location picker** — the picker searches active districts, their cities, neighbourhoods and alternate names (`/api/locations?q=`). Check `/admin/districts` for a district accidentally disabled, or `SELECT COUNT(*) FROM districts;` (should be 780) in case `010_india_locations.sql` hasn't been applied yet (`php bin/migrate.php`).

**No events show for a selected district** — genuinely correct if that district has no published events yet (check `/admin/events?district_id=N`) — the empty state is intentional, not a bug (see [Event Discovery Sources](#event-discovery-sources); this platform never fabricates events to fill a page). If you expect events to be there, confirm the event's venue's city actually has `cities.district_id` set to that district.

**Wrong district's events appear after switching** — confirm the browser actually completed the `/api/set-district` request before the page reloaded (check the Network tab); a slow request racing the reload is the most common cause. Server-side, `HomeController`/`EventController`/`DashboardController` all resolve the active district the same way (logged-in preference → `$_SESSION['district_id']` → Chennai) — if one page disagrees with another, check that controller didn't miss reading `district_id` from the request/session (grep for a stray `city_id` fallback).

**Event source unavailable / "Run Now" fails immediately** — check the source has both `enabled = 1` and a non-empty `adapter_class`; `/admin/sources/{id}` shows the last run's error message. A per-URL fetch failure (dead link, blocked by SSRF guard, timeout) is expected and handled gracefully — the run still completes, it just records `records_error > 0` for that URL rather than crashing.

**"Search provider not configured"** — correct by default: no licensed provider is bundled and search engines are never scraped (see [Event Discovery Sources](#event-discovery-sources)). Discovery keeps working through direct adapters, approved social APIs and user posting.

**A social connector can't be enabled / "is not configured"** — its credentials aren't in `.env` (or `config_json` lacks accounts). `/admin/sources` shows exactly which variables are missing. That's intentional: an adapter class existing doesn't mean the platform granted access.

**Instagram/Facebook API error "(#10) … permission" / "Unsupported get request"** — the token lacks the permission for that account/page, the account isn't a professional account linked to your Meta app, or `META_GRAPH_API_VERSION` is too old/new for your app. Fix the Meta app configuration — do not work around it by scraping.

**Expired events still visible** — upcoming listings always filter on a future `event_occurrences.start_at_utc`, and `php bin/scheduler.php expire-events` marks finished events `completed`. If one still shows, check for a custom query that skipped the date filter.

**Duplicate event showing twice** — every creation path (user posting, ingestion) runs `DuplicateDetectionService`; scores 60–84 land in `/admin/events/duplicates` for a human decision rather than being merged automatically. Two cards for one event usually means a pair is waiting there — merge it.

**Posted event isn't in the list** — check My Events: "Under review" means a risk signal held it (the reason is on the success page / email); an online event has no district, so it won't appear under a district filter; a cancelled/completed event is intentionally hidden from upcoming lists.

**Emails queued but never arrive** — `SELECT status, retry_count, error_message FROM notification_jobs ORDER BY id DESC LIMIT 20;`. `queued` = the scheduler isn't running (`php bin/scheduler.php send-emails`); `failed` = SMTP problem, see `notification_email_failed` in `storage/logs/app-*.log`.

**Source parser failure** — `raw_event_records.processing_status` is `rejected` / `needs_review` / `error` with `error_message` explaining why (social filter reason, event already past, district couldn't be determined, low confidence…); check `storage/logs/app-*.log` for `source_fetch_failed` too.

**`/event/{slug}/calendar.ics` 404s with `php -S`** — use `router.php` (it now also pins `SCRIPT_NAME`, which the built-in server otherwise sets to any path containing a file extension, breaking routing).

Logs: uncaught exceptions, fatal errors and PHP warnings/notices are written to `storage/logs/app-YYYY-MM-DD.log` (rotated daily, 14 days kept) via Monolog. Anything that happens before the app finishes bootstrapping, or a genuine PHP parse error in a file, still goes to your web server's own error log instead (XAMPP: `xampp/apache/logs/error.log`, or the terminal running `php -S`).

## Security Notes

- Never commit `.env` — it's already listed for denial in `public/.htaccess`, and `app/`, `config/`, `database/` (containing `.env`) sit outside the web root regardless.
- Set `APP_DEBUG=false` in any environment reachable by anyone other than you.
- Passwords are hashed with `PASSWORD_ARGON2ID` (falls back to bcrypt if Argon2id isn't available) — never stored or logged in plaintext.
- Every state-changing request (POST/PUT/DELETE/PATCH) is checked against a per-session CSRF token by `CsrfMiddleware`.
- The "Register Now" redirect and every source-ingestion HTTP fetch go through SSRF protections (`RegistrationRedirectService`, `AbstractSourceAdapter::httpGet`) that block localhost/private IP ranges and non-http(s) schemes, and re-validate every redirect hop — do not bypass these when adding new outbound-URL features. User-entered URLs are additionally checked by `UrlValidator` at submission.
- User content is plain text: HTML tags are rejected, output is always escaped, and values placed in JSON-LD / inline JS use `JSON_HEX_TAG|JSON_HEX_APOS|…` (a stored-XSS hole in the event page's Share button — `addslashes(View::e(title))` inside `onclick` — was fixed as part of adding user posting).
- Uploads: magic-byte + decode validation, random filenames, no user-controlled paths, script execution disabled in `public/uploads/` (`.htaccess`). On nginx, add an equivalent `location ^~ /uploads/ { ... }` rule that never passes files to PHP.
- Social/search API tokens live only in `.env`; they are never written to logs, error messages or the database.
- Use a dedicated, least-privilege MySQL user in production rather than `root`.
- Back up the database regularly; rotate `APP_SECRET` and any leaked API keys immediately if exposed.

## Testing

End-to-end acceptance suite (real MySQL + the real HTTP stack via PHP's built-in server on `127.0.0.1:8099`):

```bash
php bin/test-platform.php
```

It covers: signup with WhatsApp number (normalization, required, no WhatsApp jobs, email verification); access control and login-return; user A posting an AI workshop in Chennai with a poster; user B seeing it on `/discover`, detail page, Save, Register Now, Google Calendar link and `.ics`; district filtering (Coimbatore vs Chennai); owner edit + other users blocked (404) + mass assignment ignored + saver notified; duplicate blocking/review; XSS, fake-image upload, bad URLs, expired dates; reports, cancel, delete rules; email queue/reminders/digests delivered through a capturing mailer; Instagram/Facebook/X connectors against API-shaped fixtures (publish, cross-source merge into one event, recap/job/ad/past rejection, low-confidence review) and JSON-LD (timezone, district, source-side cancellation); **keyless discovery** against a local fixture website (`tests/fixtures/site/router.php`, on `127.0.0.1:8098`): crawler pagination, relative links, JSON-LD + microdata, robots.txt-disallowed page never requested, other hosts ignored, crawler + ICS + RSS copies merged into one event, higher-trust ICS updating venue/end time, lower-trust RSS logged as a conflict instead of applied, a re-crawl after the site changed a start time updating the record, a user-posted event flagged rather than overwritten, a national-platform listing whose event URLs appear only in JSON-LD, an Indian event outside TN kept and labelled "Mumbai, Maharashtra" while an event in Dubai is rejected, and categories (AI, cycling, MusicEvent → Culture, a user's own category untouched); expiry; admin moderation and audit log. Test data uses `@nevents-test.local` emails and `[T-…]` titles and is removed at the start of the next run (so the last run stays browsable). No real email is sent unless you pass `--send-email=you@example.com`.

PHPUnit is configured (`composer test`) with empty `tests/Unit/` + `tests/Integration/` folders.

## Known Gaps / What's Not Finished

Being direct about this rather than overstating completeness:

- **Social connectors have never called a live API** — Instagram, Facebook and X are implemented against their documented endpoints and tested with API-shaped fixtures only; no credentials exist in this environment. Supply the variables in [Environment Variables](#environment-variables), then use **Run Now** on each source before relying on it. Endpoint/field availability depends on your Meta app review and X access tier.
- **No search provider** — `SearchDiscoveryProviderInterface` has no licensed implementation; search-based discovery is off.
- **Google Calendar OAuth sync** — not implemented (links + `.ics` only).
- **No thumbnails** — the GD extension isn't enabled in this XAMPP build, so uploaded posters are stored as-is (size/dimension-limited) and scaled with CSS.
- **The scheduler must be scheduled** — `bin/scheduler.php` does nothing unless cron / Task Scheduler runs it every 5 minutes (see [Background Jobs](#background-jobs)).
- **Demo events are still visible on the live site** — the 5 seeded events (`data_origin = 'demo'`) remain shown because they're the only content in a fresh install; once real sources/organizer submissions exist, exclude them everywhere with one filter change (`'exclude_demo' => true` in any `EventRepository` call). They are already correctly excluded from duplicate-matching against genuinely discovered events.
- **Platform terms not verified** — Meetup, Eventbrite, District and EventsFlare are enabled on the owner's instruction. robots.txt allows the pages used, but their terms of service weren't reviewed. The generic crawler, ICS, JSON-LD, sitemap and RSS templates still ship disabled.
- **Recurring listings show one card per date** — e.g. a venue's weekly party listed for three dates becomes three events. They are separate dates, so duplicate detection correctly leaves them alone. Grouping them into one event with several occurrences isn't implemented.
- **An address without a city name isn't placed** — e.g. "Udayampalayam, Tamil Nadu 641028" goes to review because PIN codes aren't mapped to districts.
- Many sites don't publish schema.org Event data. The crawler finds nothing on those by design, because it doesn't guess events from free text.
- **"Contact Us" doesn't persist** — `PageController::contactPost()` still only shows a success message. (Event submission now goes through `/events/create`, which does persist.)
- **Duplicate detection is heuristic** (same-day + title/district/start-time/venue/registration-URL/organizer scoring) — no image similarity; uncertain pairs go to `/admin/events/duplicates` for a human.
- **Organizer logos** aren't uploadable yet (event posters are).
- **Search** runs on a MySQL `FULLTEXT` index (title + summary only) — the `SEARCH_DRIVER`/Meilisearch env vars are reserved for a future dedicated search engine, not yet implemented; the discover-page district filter and the search page are two separate query paths that both work today, but the search page doesn't yet expose a district dropdown in its UI (the filter parameter exists and works if you construct the URL, e.g. `/search?q=startup&district=2`).

## Final Run Checklist

- [ ] `composer install` completed without errors
- [ ] `.env` created from `.env.example` and `DB_PASSWORD`/`APP_URL` set correctly
- [ ] MySQL running, `php bin/migrate.php` completed (database + 50+ tables + seed data)
- [ ] `SELECT COUNT(*) FROM districts;` returns 38
- [ ] Homepage location picker finds places anywhere in India (try "Karaikal", "Secunderabad", "Kerala"); picking one actually changes the event sections shown, and "All India" resets it
- [ ] `/admin/districts` shows all 780 (filterable by state) with correct per-district event counts
- [ ] `php bin/create-admin.php` completed
- [ ] App reachable in the browser (`http://localhost:8080` or your XAMPP URL) with the homepage showing seeded demo events
- [ ] Logged in as the admin account, `/admin` dashboard loads with real counts
- [ ] Registered a new account, completed onboarding, saved an event, confirmed it shows on `/my-events/saved`
- [ ] Confirmed the new user row in `users` via phpMyAdmin/MySQL CLI (see [Database Setup & How to View Data](#database-setup--how-to-view-data)) — `password_hash` is a real hash, `email_verified_at` is `NULL`
- [ ] Configured `MAIL_*` with real SMTP credentials and confirmed a verification email actually arrives (`storage/logs/app-*.log` shows `verification_email_sent`, not `_failed`)
- [ ] Clicked the verification link and confirmed `email_verified_at` gets a timestamp, then logged in successfully
- [ ] Confirmed `REQUIRE_EMAIL_VERIFICATION=false` lets an unverified account log in, and `=true` blocks it again — both without altering `email_verified_at`
- [ ] `php bin/test-platform.php` ends with `0 failed`
- [ ] Posted an event at `/events/create`, saw "Your event has been published.", found it on `/discover` for its district while logged in as a *different* user
- [ ] `php bin/scheduler.php --list` works, and Task Scheduler / cron runs `php bin/scheduler.php` every 5 minutes
- [ ] With SMTP configured, `php bin/scheduler.php send-emails` moves `notification_jobs` rows to `sent`
- [ ] `/admin/sources` shows each social connector as "Needs: …" until its credentials are added
- [ ] `APP_DEBUG=false` before any non-local deployment
