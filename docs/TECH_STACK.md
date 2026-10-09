# Singapore Journal of Cardiology — Tech Stack

Repository snapshot: **10 October 2026**. Website: [sjcjournal.com](https://sjcjournal.com). This document describes the implemented application and distinguishes the live Hostinger deployment from the Docker reference environment.

## Stack at a glance

| Layer | Technology | Purpose |
| --- | --- | --- |
| Application | PHP 8.4, Laravel 13 | Routing, validation, business rules, publishing and administration |
| User interface | Laravel Blade, Tailwind CSS 4, custom CSS, vanilla JavaScript | Server-rendered public website and role portals |
| Asset build | Node.js 22, Vite 8, Laravel Vite plugin | Compile CSS, JavaScript and the PDF reader worker |
| Live database | SQLite | Journal content, accounts, workflows, notifications and queue records on Hostinger |
| Docker database | PostgreSQL 17 | Development/reference deployment and database-specific CI coverage |
| Cache and sessions | Laravel stores; Redis 7.4 in Docker | Cache, throttling, session persistence and scheduler locks, according to environment configuration |
| Authentication | Laravel sessions, email verification, Sanctum | Browser login, account verification and scoped API tokens |
| Authorization | Custom roles/permissions, middleware, gates and policies | Super Admin, Admin, Editor, Reviewer, Author and Contributor access |
| Background work | Laravel database queue and scheduler | Email, workflow reminders, scheduled publishing and maintenance |
| Email | Hostinger SMTP over SMTPS | Account, submission, review, decision and contact communications |
| File storage | Laravel Filesystem / Flysystem; local private and public disks | Manuscripts, published PDFs, images and profile photos |
| PDF | PDF.js and Dompdf | Browser reading and generated acceptance letters |
| Analytics and SEO | Consent-based GA4, Laravel metadata and JSON-LD | Public reader analytics, canonical URLs, sitemap and scholarly metadata |
| Hosting and DNS | Hostinger shared hosting; GoDaddy domain/DNS | Live PHP application, mailbox hosting and domain routing |
| Delivery and quality | Git/GitHub, GitHub Actions, PHPUnit, Pint, Node test runner, Docker | Version control, automated checks and repeatable builds |

The interface uses Blade templates and JavaScript enhancements. It does not require a separate frontend application server in production.

## Locked dependency versions

Versions below come from [composer.lock](../composer.lock) and [package-lock.json](../package-lock.json), rather than the broader allowed ranges in the manifests.

| Package | Locked version | Use |
| --- | --- | --- |
| `laravel/framework` | 13.34.0 | Application framework |
| `laravel/sanctum` | 4.3.3 | Personal access tokens and stateful API support |
| `dompdf/dompdf` | 3.1.6 | Acceptance letter PDF generation |
| `firebase/php-jwt` | 7.1.0 | Google identity token verification |
| `league/flysystem-aws-s3-v3` | 3.35.3 | Optional S3-compatible storage adapter |
| `phpunit/phpunit` | 12.5.33 | PHP unit and feature tests |
| `laravel/pint` | 1.30.5 | PHP formatting checks |
| `vite` | 8.2.2 | Frontend asset build |
| `laravel-vite-plugin` | 3.2.0 | Laravel asset manifest integration |
| `tailwindcss` / `@tailwindcss/vite` | 4.3.3 | CSS utilities and build plugin |
| `pdfjs-dist` | 6.3.289 | In-page PDF reader and worker |

## Runtime and build requirements

- **PHP:** the Composer requirement is `^8.4.1`; Docker and CI use the PHP 8.4 line. Use the extensions required by Composer and the selected database driver. The Docker image installs `bcmath`, `dom`, `exif`, `gd`, `intl`, `mbstring`, `opcache`, `pcntl`, `pdo_pgsql`, `pdo_sqlite`, `xml`, `xmlwriter`, `zip` and the Redis extension.
- **Composer:** version 2, with production dependencies installed from the lockfile.
- **Node.js:** use **22.13.0 or newer within the 22.x line** for builds. This satisfies both the locked Vite and PDF.js engine requirements. Node is a build dependency; the live PHP request path does not need a Node server.
- **Database:** SQLite with `pdo_sqlite` for the current Hostinger installation; PostgreSQL with `pdo_pgsql` for the Docker reference environment.
- **Containers:** the reference images are PHP 8.4-FPM Alpine, Nginx 1.29 Alpine, PostgreSQL 17 Alpine, Redis 7.4 Alpine and Mailpit 1.27. These tags identify image lines, not immutable digest pins.

Sources: [composer.json](../composer.json), [package.json](../package.json), [Dockerfile](../Dockerfile), [docker-compose.yml](../docker-compose.yml), [CI workflow](../.github/workflows/ci.yml).

## Environment differences

| Concern | Current live Hostinger deployment | Docker reference environment |
| --- | --- | --- |
| Web serving | Hostinger-managed web/PHP runtime | Nginx → PHP-FPM container |
| Persistent database | Private SQLite database | PostgreSQL 17 volume |
| Queue backend | Database | Database, even though Redis is available |
| Queue execution | Minutely cron starts a bounded worker | Dedicated `queue` service |
| Scheduling | Minutely cron runs `schedule:run` | Dedicated `scheduler` service runs `schedule:work` |
| Cache/session selection | Private runtime configuration; repository defaults are database stores | Redis cache and database sessions |
| Media | Private app storage and public storage mapping | Shared durable storage volume |
| Email | Hostinger SMTP | Mailpit capture for local development |
| Built assets | Deployed `public/build` assets | Built into Docker images |

Docker publishes the website on loopback port **8080 by default**, configurable through `APP_PORT`. Mailpit's local inbox is available on port **8025**. These are local development ports, not production endpoints.

## Implemented integrations

| Integration | Current status |
| --- | --- |
| Hostinger email | Configured sender: `notifications@sjcjournal.com`; office alerts use `info@larixjournals.com` according to publication settings. Mailbox credentials stay in private runtime configuration. |
| Google Analytics 4 | Measurement ID `G-VPMDF48T9K`; optional tags load only after consent on eligible anonymous public pages. See the analytics report for the remaining Realtime verification limitation. |
| Microsoft Clarity | Deferred and disabled. |
| Google sign-in | Implemented as an optional configured integration; it does not bypass account approval. |
| DOI registration | Manual provider and recorded workflow evidence; no automated Crossref/DataCite submission integration is bound. |
| Similarity checks and indexing | Editorial workflow records results and evidence; no external plagiarism or indexing API is wired by default. |
| S3-compatible storage | Adapter available; the current live installation uses local storage. |
| Azure | Optional transport/container support exists; Hostinger is the current deployment target. |

## Quality checks

The [CI workflow](../.github/workflows/ci.yml) installs locked dependencies, audits Composer packages, runs migrations and PHP tests against a dedicated PostgreSQL test database, checks Pint formatting, builds frontend assets and builds the production Docker target. The Docker `test` service uses an isolated in-memory SQLite database.

The analytics JavaScript tests are a separate Node test suite; the current CI workflow does not invoke them. They can be run with:

```bash
node --test tests/JavaScript/public-analytics.test.mjs
```

For development setup and the full check commands, see the [README](../README.md). For system structure and business flows, see [Architecture](ARCHITECTURE.md). For current feature/deployment evidence, see [Workflow verification](WORKFLOW_VERIFICATION_20261008.md), [Profile photos](PROFILE_PHOTOS_20261009.md), [Mail setup](MAIL_SETUP.md) and [Analytics and SEO](ANALYTICS_SEO_20261009.md).
