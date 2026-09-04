# Singapore Journal of Cardiology

Singapore Journal of Cardiology is a production-oriented scholarly and professional publication platform built with Laravel 13, PHP 8.4, PostgreSQL, Redis, Blade, Tailwind CSS, and Laravel Sanctum. It supports public discovery, long-form reading, author submissions, editorial review, reviewer feedback, scheduled publishing, media, newsletters, contact management, SEO, analytics, and auditable role-based administration.

## What is included

- Premium responsive public publication pages, searchable article archive, categories, author profiles, sitemap, structured data, print and uploaded-PDF access.
- Verified author accounts, draft autosave, co-authors, supporting documents, submission and revision history.
- Editor and reviewer assignment, immutable reviews, controlled state transitions, deadlines, notifications, and an audit trail.
- Super Admin, Admin, Editor, Reviewer, Author, and User roles enforced through middleware, policies, and server-side authorization.
- Administrative article, taxonomy, author, user, media, enquiry, subscriber, settings, analytics, and audit-log management.
- PostgreSQL-aware indexes and search abstraction, queued notifications, scheduled publication, Redis caching, and S3-compatible storage.
- Docker images for PHP-FPM and Nginx, PostgreSQL and Redis services, a dedicated queue worker and scheduler, and a CI pipeline.

## Requirements

The recommended path requires Docker Desktop or Docker Engine with Compose. A native installation requires PHP 8.3 or newer with `bcmath`, `ctype`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `tokenizer`, and `zip`; Composer 2; Node.js 22; PostgreSQL 15+; and Redis 7+.

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

New applications remain signed out, inactive, unverified, and without permissions until a Super Admin approves them. The requested role is taken from the server-owned registration route, not from editable form input. Approval activates the account, assigns only the requested role, and starts email verification. Rejection does not grant portal access.

Super Admins control users, applications, roles, permissions, and site settings. Admins operate publication content, Editors manage the editorial workflow, Reviewers access only assigned manuscripts, and Authors manage only their own work. There is no public Super Admin registration path.

## Workflow email and Google sign-in

The application queues both database and email notifications for submission receipts, new editorial submissions, reviewer assignments (including reassignment or changed due dates), completed reviewer reports, and article status decisions. Authors never receive confidential reviewer notes by email.

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

- [Architecture and editorial workflow](docs/ARCHITECTURE.md)
- [Production deployment](docs/DEPLOYMENT.md)
- [PostgreSQL, media, backup, and restore](docs/BACKUP_AND_RESTORE.md)
- [REST API](docs/API.md)
- [Security policy](SECURITY.md)

## License

This project is proprietary unless the repository owner supplies a different license.
