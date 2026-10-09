# Singapore Journal of Cardiology

Singapore Journal of Cardiology is a scholarly and professional publication platform built with Laravel 13, PHP 8.4, Blade, Tailwind CSS, vanilla JavaScript and Laravel Sanctum. The live [sjcjournal.com](https://sjcjournal.com) installation uses SQLite and queued SMTP email on Hostinger shared hosting; the Docker reference environment uses PostgreSQL and Redis. It supports public discovery, long-form reading, author submissions, editorial review, reviewer feedback, scheduled publishing, media, newsletters, contact management, SEO, analytics, and auditable role-based administration.

See the [Tech Stack](docs/TECH_STACK.md) for locked versions and environment differences, and [Architecture](docs/ARCHITECTURE.md) for system diagrams, account approval, editorial flows and notification delivery.

## What is included

- Premium responsive public publication pages, searchable article archive, categories, author profiles, sitemap, structured data, print and uploaded-PDF access.
- Verified author accounts, draft autosave, co-authors, supporting documents, submission and revision history.
- Editor and reviewer assignment, immutable reviews, controlled state transitions, deadlines, notifications, and an audit trail.
- Super Admin, Admin, Editor, Reviewer, Author, Contributor and legacy User roles enforced through middleware, policies, and server-side authorization.
- Administrative article, taxonomy, author, user, media, enquiry, subscriber, settings, analytics, and audit-log management.
- PostgreSQL-aware indexes and search abstraction, queued notifications, scheduled publication, Redis caching, and S3-compatible storage.
- Docker images for PHP-FPM and Nginx, PostgreSQL and Redis services, a dedicated queue worker and scheduler, and a CI pipeline.

## Requirements

The recommended development path requires Docker Desktop or Docker Engine with Compose. A native installation mirroring that environment requires PHP 8.4.1 or newer with the required extensions including `bcmath`, `ctype`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `tokenizer`, and `zip`; Composer 2; Node.js 22.13+ within the 22.x line; PostgreSQL and Redis. Docker uses PostgreSQL 17 and Redis 7.4. The Hostinger installation instead uses SQLite with `pdo_sqlite` and its private runtime configuration; see the [environment comparison](docs/TECH_STACK.md#environment-differences).

## Docker quick start

```bash
cp .env.example .env
```

Set a unique `DB_PASSWORD`, then generate an application key without saving it to shell history:

```bash
docker compose build app
docker compose run --rm app php artisan key:generate --show
```

Copy the displayed value into `APP_KEY` in `.env`, then initialize the application:

```bash
docker compose up -d postgres redis
docker compose run --rm app php artisan migrate --seed
docker compose up -d
```

The publication is available at `http://localhost:8080`. Local email is captured by Mailpit at `http://localhost:8025`.

Create the first administrator interactively:

```bash
docker compose exec app php artisan user:create-admin
```

For local UI development only, `LOCAL_ADMIN_BYPASS_ENABLED=true` grants a dedicated, request-scoped identity access to `/admin`. The bypass is rejected unless `APP_ENV=local`, `APP_BIND_ADDRESS` is loopback-only, the request host is loopback, and the raw connection address appears in `LOCAL_ADMIN_ALLOWED_REMOTE_ADDRS`. Docker Desktop may present local traffic through its bridge gateway; add that one observed gateway address to the local `.env` when needed. The identity is inactive, unverified, roleless, and logged out between requests, so it cannot be reused through login or password reset. Leave the bypass disabled everywhere else.

Default seeding installs only roles, permissions, and settings. To add the rich sample publication locally, set `SEED_DEMO_CONTENT=true` before running the seeder. The demo seeder refuses to run outside `local` and `testing`, and its accounts receive random, unrecoverable passwords.

Never place a real production password in a seeder or committed environment file. Automated baseline seeding creates an admin only when both `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` are explicitly present at runtime; interactive `user:create-admin` remains the preferred production path.

## Role portals and account approval

Each staff role has a dedicated sign-in and application page: `/author/login`, `/editor/login`, `/reviewer/login`, and `/admin/login`, with matching `/author/register`, `/editor/register`, `/reviewer/register`, and `/admin/register` application routes.

New applications receive an email verification link immediately and remain signed out, inactive, and without permissions until a Super Admin approves them. Portal access requires both a verified email address and approval, in either order. The requested role is taken from the server-owned registration route, not from editable form input. Approval activates the account and assigns only the requested role; rejection does not grant portal access.

Super Admins control users, applications, roles, permissions, and site settings. Admins operate publication content, Editors manage the editorial workflow, Reviewers access only assigned manuscripts, and Authors manage only their own work. There is no public Super Admin registration path.

Registered users can upload a public profile photo from their role portal; administrators use My account. Approved users with verified email and an uploaded photo appear in Member profiles, linked from Contributors. Author bylines, editorial profiles and approved comment photos use the uploaded image. See [public profile photos](docs/PROFILE_PHOTOS_20261009.md) for the behavior and verification.

## Workflow email and Google sign-in

The application queues both database and email notifications for submission receipts, new editorial submissions, reviewer assignments (including reassignment or changed due dates), completed reviewer reports, and article status decisions. Authors never receive confidential reviewer notes by email. Workflow updates include the manuscript title, ID, stage, applicable deadline, and a link to the relevant manuscript or review. Submission and publication events send one author notification per event. See the [8 October workflow verification](docs/WORKFLOW_VERIFICATION_20261008.md) for the tested journey and deployment scope.

For local development, leave the SMTP settings pointed at Mailpit and inspect messages at `http://localhost:8025`. For a live mail provider, set `MAIL_MAILER=smtp` and provide the provider host, port, username, password, encryption scheme, and verified `MAIL_FROM_ADDRESS` in the deployment environment. The queue worker must remain running for delivery.

Google sign-in is optional. Create a Google OAuth **Web application** client, register `${APP_URL}/auth/google/callback` as its redirect URI, then set `GOOGLE_OAUTH_ENABLED=true`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI`. Every role's first Google sign-in creates the same pending application as its registration page; a Super Admin must approve it before access is granted.

## Native development

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan serve
```

Run the queue and scheduler in separate processes:

```bash
php artisan queue:work --queue=default,mail --tries=3
php artisan schedule:work
```

For public local uploads, run `php artisan storage:link`. Set `MEDIA_DISK=s3` for an S3 or S3-compatible provider and configure the `AWS_*` variables.

## Verification

```bash
docker compose run --rm test
docker compose run --rm app vendor/bin/pint --test
docker run --rm -v "$PWD:/app" -w /app node:22-alpine npm ci
docker run --rm -v "$PWD:/app" -w /app node:22-alpine npm run build
docker compose config --quiet
```

The dedicated test service always uses an isolated in-memory SQLite database. As a safety boundary, the application refuses to start Laravel's Artisan test runner unless both the testing environment and an isolated test database are configured. CI additionally runs the migration and feature suite against a dedicated PostgreSQL test database so database-specific behavior is covered.

## Configuration map

- Application and security: `APP_*`, `SESSION_*`, `LOGIN_RATE_LIMIT`, `CONTACT_RATE_LIMIT`, `TRUSTED_PROXIES`.
- PostgreSQL and Redis: `DB_*`, `REDIS_*`, `CACHE_STORE`, `QUEUE_CONNECTION`.
- Media: `FILESYSTEM_DISK`, `MEDIA_DISK`, `UPLOAD_MAX_KILOBYTES`, `AWS_*`.
- Email and newsletter: `MAIL_*`, `CONTACT_NOTIFICATION_EMAIL`, `MAIL_PROVIDER`, `NEWSLETTER_PROVIDER`.
- Optional integrations: `GOOGLE_ANALYTICS_ID`, `GOOGLE_SEARCH_CONSOLE_VERIFICATION`, reCAPTCHA or Turnstile keys.
- Publication switches: `COMMENTS_ENABLED`, `AUTHOR_REGISTRATION_ENABLED`, `REVIEWER_WORKFLOW_ENABLED`, `PDF_DOWNLOADS_ENABLED`, `NEWSLETTER_ENABLED`.
- Seed controls: `SEED_DEMO_CONTENT` (local/testing only), `SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`.

Private keys belong only in the runtime secret store. Variables prefixed with `VITE_` are compiled into browser assets and must never contain secrets.

## Documentation

- [Tech stack, locked versions and environment comparison](docs/TECH_STACK.md)
- [Architecture and editorial workflow](docs/ARCHITECTURE.md)
- [Container/VPS deployment reference](docs/DEPLOYMENT.md)
- [PostgreSQL, media, backup, and restore](docs/BACKUP_AND_RESTORE.md)
- [REST API](docs/API.md)
- [Hostinger mail configuration](docs/MAIL_SETUP.md)
- [Author/editor/reviewer workflow verification](docs/WORKFLOW_VERIFICATION_20261008.md)
- [Public member profile photos](docs/PROFILE_PHOTOS_20261009.md)
- [GA4 and SEO configuration](docs/ANALYTICS_SEO_20261009.md)
- [Security policy](SECURITY.md)

## License

This project is proprietary unless the repository owner supplies a different license.

## Five curated demo journal articles

To add five realistic technology, farming, soil, energy, and food-system demo articles locally:

```bash
docker compose exec -e SEED_DEMO_CONTENT=true app php artisan db:seed --class=DemoJournalSeeder
```

These are ordinary published records managed through **Admin → Articles**, including editing and deletion. Each is clearly identified as illustrative content. Re-running this seeder preserves existing edits and soft deletions using stable demo article numbers; it does not recreate deleted articles. It does not run automatically on website startup.

For local author-portal development, set `LOCAL_AUTHOR_BYPASS_ENABLED=true` and open `/author/dashboard`. This uses the same loopback binding and allowed connection addresses as the admin bypass, with a separate request-scoped author identity and author permissions only. Both bypasses default to disabled and require `APP_ENV=local`. The existing `is_local_admin_bypass` database marker identifies both temporary local identities; neither remains active or has persisted roles after a request.

The editor and reviewer dashboards support the same local-only workflow with `LOCAL_EDITOR_BYPASS_ENABLED=true` and `LOCAL_REVIEWER_BYPASS_ENABLED=true`. Open `/editor/dashboard` or `/reviewer/dashboard`. Each receives its own role-limited temporary identity; the switches default to false and share the loopback restrictions above.
