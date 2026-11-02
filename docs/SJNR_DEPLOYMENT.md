# SJNR deployment and journal settings

Prepared 2 November 2026. This branch reuses the SJC application for the Singapore Journal of Nursing Research. The SJC profile remains the default. Select `JOURNAL_PROFILE=sjnr` and the matching application name for an SJNR installation.

The preview target is `https://lightgreen-leopard-945544.hostingersite.com`. The intended final domain is `https://sjnrjournal.com`. Keep the current final-domain website online while reviewing the preview. This document does not record a completed final-domain cutover or working SMTP delivery.

## Where to change journal content and SEO

| Item | Where to change it |
| --- | --- |
| Journal identity, nursing keywords and page descriptions | `config/journal_profiles.php`, `sjnr` entry |
| Active journal profile | Private `.env`: `JOURNAL_PROFILE=sjnr` |
| Journal/application name in email and system screens | Private `.env`: `APP_NAME`, plus Admin > Settings > General > Website name |
| Public site description and contact addresses | Admin > Settings > General |
| ISSN, publisher, subject areas and publication frequency | Admin > Settings > Publication |
| Article title, description, keywords, canonical URL and social metadata | Admin > Articles > edit > Search & sharing metadata |
| Category SEO | Admin > Categories > edit > SEO title / description |
| Aims and scope, author instructions and other journal policies | Admin journal pages / policy settings; publish approved SJNR content |
| Site URL, sitemap origin, sessions and storage paths | Private `.env`; use `.env.sjnr.example` as the non-secret reference |
| Indexing of the preview | `SEARCH_INDEXING_ENABLED=false`; the preview public `.htaccess` also applies noindex to static files |
| Analytics, search-console verification and Google sign-in | Private `.env`; use SJNR-specific properties and approved credentials |

Administrator settings override profile defaults. Baseline seeding preserves saved values. `journal:initialize-sjnr` is a one-time initializer for an unused SJNR database; it refuses an already initialized database or one containing accounts, articles or workflow files. Subsequent editorial changes belong in the admin portal.

## Hostinger layout

This is a PHP 8.4 / Laravel 13 application with MySQL, Blade views and a Vite build for CSS/JavaScript. Add a PHP/HTML website. Its JavaScript build produces assets in `public/build`; that folder is not a standalone Node.js website. The deployed PHP version is 8.4.27.

```text
/home/u709299470/domains/lightgreen-leopard-945544.hostingersite.com/
  journal_app/                    private Laravel application
    .env                         private deployment configuration
    vendor/                      installed production PHP dependencies
    storage/app/private/         private manuscripts and workflow files
  public_html/                   web document root
    index.php                    prepared Laravel bridge
    .htaccess
    build/manifest.json
    build/assets/
    storage/                     directly mapped public media directory
```

Set `APP_PUBLIC_PATH` to the absolute `public_html` path and `PUBLIC_DISK_ROOT` to its `storage` directory. The application uses the same public path for browser and scheduled execution. With this direct mapping, no storage symlink is needed. Do not move private manuscripts into the public directory.

The SJNR release excludes the original SJC author-resource forms. It imports the existing SJNR guidelines, copyright form, cover letter and five manuscript templates. Resource links appear only when their imported files exist. Source PDFs for SJC remain intact in the repository for the SJC profile.

## Separate database and initialization

The preview has its own MySQL database and user, `u709299470_sjnr_preview`. PHP connects through `DB_HOST=localhost` using the server's local MySQL socket. This connection was verified before changing the setting. `DB_PERSISTENT=true` enables PDO connection reuse for the SJNR deployment; the application's default remains false for other installations. Hostinger documents a limit of 20 new MySQL connections per second and recommends persistent connections for the observed `SQLSTATE[HY000] [2002] Operation not permitted` error during traffic bursts ([Hostinger guidance](https://www.hostinger.com/support/how-to-fix-mysql-operation-not-permitted-error/)). Its generated password and application key stay in private deployment configuration outside source control. Do not copy SJC accounts, private manuscripts, queues or SMTP credentials.

One hPanel cron entry runs every minute:

```text
/opt/alt/php84/usr/bin/php /home/u709299470/domains/lightgreen-leopard-945544.hostingersite.com/journal_app/scripts/hostinger-sjnr-scheduler.php
```

The CLI-only wrapper first calls `scripts/hostinger-initialize-sjnr.php`. It requires the SJNR profile, the resolved MySQL connection to `u709299470_sjnr_preview` on the local server, a Hostinger temporary URL, and `SJNR_INITIALIZE_ALLOWED=true`. It rejects demo/admin seed credentials and databases with existing journal records, applies migrations and baseline roles/settings, initializes nursing identity/categories, records a private completion marker, and disables initialization.

On the next run, `scripts/hostinger-import-sjnr-public.php` imports the verified public dataset. It uses a suspended account with no roles solely to record import provenance. It provisions the first website administrator from a private, generated handover file and removes the server-side plaintext credential file after success. It records a separate completion marker, disables importing, and ends maintenance mode. Later runs execute Laravel's normal `schedule:run`. Locks prevent overlapping runs. Do not add separate initialization or scheduler jobs for this preview.

The private application directory has owner-only access (`0700`); `.env` has `0600`. Only the bridge, built assets and public media belong under `public_html`.

Initialization and public importing completed on 2 November 2026. The live database has nine articles, 20 public author records, 15 editorial profiles and two application accounts: the suspended provenance identity and the initial administrator. The one-off repair refreshed legacy redirects using persisted canonical slugs and verified the socket connection. Its temporary cron was removed; only the scheduler wrapper remains for this preview. Existing SJC cron entries were preserved.

## Public content carried forward

The source snapshot includes nine published articles and nine original PDFs, 20 deduplicated author records, 15 editorial profiles, 19 journal pages, 38 raster images and eight forms/templates. The importer verifies file hashes and signatures before writing, retains original article metadata and PDFs, and maps 95 changed legacy URLs to their new local destinations. It does not copy old website accounts, unpublished submissions or private workflows. Repeated imports preserve subsequent editorial changes.

The original `/editor-guidelines` page returned HTTP 500 and could not be imported. The old external direct-payment link remains on the original website; no payment gateway was configured for the preview. Indexing claims were not inferred from unverified source links.

Long article URLs use the application's persisted canonical slug. Legacy abstract/PDF links resolve to that actual slug, including the longest source title. Editorial updates to a canonical slug are retained on a repeated import. New generated slugs are trimmed at a word separator so truncation cannot leave an invalid trailing hyphen.

Hostinger's CDN can recompress delivered images. The importer rechecked all original stored file hashes; the nine article PDFs and eight downloadable forms/templates are verified separately against the source bytes.

The embedded article reader uses matching compatibility builds of PDF.js and its worker. The standard build failed while processing original PDFs in runtimes missing recent JavaScript methods. Safari also lacks the stream async iteration used by PDF.js's `getTextContent()` helper, so the reader extracts the same text chunks through `getReader()/read()`. The regression tests render two different pages and verify actual text extraction with the missing APIs removed; extraction errors continue to propagate.

## Verification record

- PHP: 296 tests passed, with 3,299 assertions, in the isolated validation checkout. The image-optimization checks used the installed native optimizer and the suite used a 512 MB memory limit.
- JavaScript: all 12 tests passed, including four PDF-reader regressions. The production Vite build succeeded.
- Live PHP/MySQL: initialization and import completed against the separate preview database. Administrator sign-in and the settings, articles, editorial-directory and journal-page screens returned successfully; the verification session was signed out afterward.
- Live public checks: 39 pages, all 55 imported assets, all 95 changed legacy URLs and 16 built/static assets passed after connection reuse was enabled. Original article PDFs and forms match source hashes; CDN image copies were checked for matching content and proportions after resizing/recompression.
- Safari acceptance: the imported seven-page article completed rendering, moved from page 1 to page 2, and exposed the original page text without a reader error after the stream compatibility fix. All five final Vite build files were also checked against local release hashes.
- The preview uses HTTPS with a valid certificate, noindex headers/meta, robots disallow, and a disabled sitemap. Sensitive configuration, vendor and private-file paths returned 403 or 404.
- The SJC site returned HTTP 200. The existing SJNR domain still resolved to its prior `207.174.214.35` address and GoDaddy nameservers; no final-domain DNS cutover was performed.

The local non-secret deployment reference is `.env.sjnr.example`. Generated credentials are stored separately in the private local handover, never in this document or Git. Existing deployment archives are initial-install artifacts and should not be reapplied over an initialized site's `.env` or storage. For future asset updates, replace only the built files while retaining `public_html/storage` and private application storage.

## Additional SJC public collection

At the owner's request, the public collection from `https://sjcjournal.com` is also copied into the preview: 39 articles, 39 PDF downloads and 38 cover images, retaining the existing nine SJNR articles. The resulting collection has 48 publications. Each copied article credits the original Singapore Journal of Cardiology publication and links to its source; the PDFs retain their original journal identity and copyright notices.

The collector is `scripts/import/sjc_public.py`; the target-specific CLI helper is `scripts/hostinger-import-sjc-public.php`. The helper validates the complete source inventory and file hashes before writing, refuses unrelated DOI/URL collisions, preserves existing accounts and records, and uses a private completion marker to prevent duplicate imports. A temporary HTTP-denied staging directory supplies public source files during deployment and is removed afterward. The import's permanent provenance and completion records remain in private application storage and database settings.

The live import completed with 39 new articles, 176 new author records and 38 covers; the article count rose from nine to 48. All 39 copied article pages returned successfully, all 39 PDF hashes matched the source downloads, and every source article appeared in the paginated listing. The temporary import cron was removed and its staging helper now returns HTTP 404. Email remains paused.

## SJC editorial board and public information

The additional public-information copy reviewed 234 source URLs: 45 journal-page entries, seven editorial profiles, 176 contributor profiles, three resource PDFs, six categories and the source homepage selections. The preview now shows the seven SJC board members with their portraits, the source About content, two featured articles, five selected latest articles, source categories, public office address/phone/WhatsApp and three resource downloads. Contributor profiles on SJC contain names and publications but no additional biographies or portraits to transfer.

The source has no published body text on 44 policy entries. Eighteen existing SJNR guidance pages were retained where richer published content already existed; the remaining empty entries keep their unpublished states. SJNR identity, ISSN, search-indexing controls, email configuration, accounts and all 48 articles were retained. Previous board records remain inactive, and a private rollback snapshot was saved before changes in `journal_app/storage/app/private/deployment/before-sjc-pages-20261102-101806.json`.

`scripts/import/sjc_pages.py` collects this public snapshot; `scripts/hostinger-import-sjc-pages.php` applies the guarded import. The resource page and navigation now read the imported `journal.resources` setting before profile defaults. Verification passed for 57 public page requests, all seven board profiles/photos, all six source categories, both homepage featured selections, and byte-identical copies of all three source PDFs. Eight journal-profile feature tests passed with 92 assertions. The temporary import cron and HTTP-denied input directory were removed after success; the removed helper returns HTTP 404.

## Hostinger email

Email configuration was deferred at the user's request. No mailbox, paid email subscription, MX change or delivery test was completed. `storage/framework/mail-paused` remains present and no mail-worker cron was added. The intended future mailbox is `notifications@sjnrjournal.com`; addresses in the preview are placeholders until that mailbox is active. Account verification, password-reset delivery and notification delivery require the later email setup.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=notifications@sjnrjournal.com
MAIL_PASSWORD="PRIVATE_MAILBOX_PASSWORD"
MAIL_FROM_ADDRESS=notifications@sjnrjournal.com
CONTACT_NOTIFICATION_EMAIL=notifications@sjnrjournal.com
ACCOUNT_NOTIFICATION_EMAIL=notifications@sjnrjournal.com
```

Domain ownership and mail routing must be verified before delivery. SJNR DNS was observed at GoDaddy on 2 November 2026. Add the exact verification TXT record shown in Hostinger, then the MX, SPF and DKIM records supplied by its email setup. Preserve the existing web A/CNAME records during preview. Do not guess DKIM keys or use SJC mailbox credentials. Keep queued mail paused until the new mailbox has been configured and authenticated.

When email setup resumes, retain the scheduler wrapper above and add the guarded `scripts/hostinger-mail-worker.php` entry using the installed PHP 8.4 binary. The worker pauses when SMTP credentials are absent or `storage/framework/mail-paused` exists. Remove the pause only after the new mailbox is authenticated, then use one approved test recipient to verify real delivery.

## Final-domain release

Before switching `sjnrjournal.com`, verify its public content, account onboarding, file access, email, database backup and rollback copy on the preview. Update `APP_URL`, `SITEMAP_BASE_URL`, the absolute public/storage paths, host-dependent integration settings, Google callback URLs if enabled, and any manually entered canonical URLs. Enable indexing only on the final domain. Preserve existing email/verification DNS records, issue SSL for both required hostnames, confirm HTTPS redirects, and inspect canonical URLs, robots, sitemap and page/social metadata after the switch.
