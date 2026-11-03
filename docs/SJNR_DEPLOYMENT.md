# SJNR deployment and journal settings

Updated 3 November 2026. This branch reuses the SJC application for the Singapore Journal of Nursing Research. The SJC profile remains the default. Select `JOURNAL_PROFILE=sjnr` and the matching application name for an SJNR installation.

The current live target is `https://sjnrjournal.com`. Hostinger has renamed the original temporary website; `https://lightgreen-leopard-945544.hostingersite.com` is retired. The web domain resolves to `89.116.133.47`, while DNS remains managed at GoDaddy. The original `sjcjournal.com` installation is separate. SMTP is configured through Hostinger, and its queue worker is enabled. See the delivery checks below.

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
| Search indexing | The live domain uses `SEARCH_INDEXING_ENABLED=true`; keep this false and add a noindex header on any future preview |
| Analytics, search-console verification and Google sign-in | Private `.env`; use SJNR-specific properties and approved credentials |

Administrator settings override profile defaults. Baseline seeding preserves saved values. `journal:initialize-sjnr` is a one-time initializer for an unused SJNR database; it refuses an already initialized database or one containing accounts, articles or workflow files. Subsequent editorial changes belong in the admin portal.

## Hostinger layout

This is a PHP 8.4 / Laravel 13 application with MySQL, Blade views and a Vite build for CSS/JavaScript. Add a PHP/HTML website. Its JavaScript build produces assets in `public/build`; that folder is not a standalone Node.js website. The deployed PHP version is 8.4.27.

```text
/home/u709299470/domains/sjnrjournal.com/
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

The initial SJNR import retained its guidelines, copyright form, cover letter and five manuscript templates. The later requested SJC content copy selected three SJC resource PDFs for the live navigation and resource page. `journal.resources` controls those links, and files appear only when they exist on the public disk. Original resource documents remain unchanged.

## Separate database and initialization

The SJNR installation has its own MySQL database and user, `u709299470_sjnr_preview`. PHP connects through `DB_HOST=localhost` using the server's local MySQL socket. This connection was verified before changing the setting. `DB_PERSISTENT=true` enables PDO connection reuse for the SJNR deployment; the application's default remains false for other installations. Hostinger documents a limit of 20 new MySQL connections per second and recommends persistent connections for the observed `SQLSTATE[HY000] [2002] Operation not permitted` error during traffic bursts ([Hostinger guidance](https://www.hostinger.com/support/how-to-fix-mysql-operation-not-permitted-error/)). Its generated password and application key stay in private deployment configuration outside source control. Do not copy SJC accounts, private manuscripts, queues or SMTP credentials.

One hPanel cron entry runs every minute:

```text
/opt/alt/php84/usr/bin/php /home/u709299470/domains/sjnrjournal.com/journal_app/scripts/hostinger-sjnr-scheduler.php
```

The CLI-only wrapper first calls `scripts/hostinger-initialize-sjnr.php`. It requires the SJNR profile, the resolved MySQL connection to `u709299470_sjnr_preview` on the local server, a Hostinger temporary URL, and `SJNR_INITIALIZE_ALLOWED=true`. It rejects demo/admin seed credentials and databases with existing journal records, applies migrations and baseline roles/settings, initializes nursing identity/categories, records a private completion marker, and disables initialization.

On the next run, `scripts/hostinger-import-sjnr-public.php` imports the verified public dataset. It uses a suspended account with no roles solely to record import provenance. It provisions the first website administrator from a private, generated handover file and removes the server-side plaintext credential file after success. It records a separate completion marker, disables importing, and ends maintenance mode. Later runs execute Laravel's normal `schedule:run`. Locks prevent overlapping runs. Do not add duplicate initialization or scheduler jobs.

The private application directory has owner-only access (`0700`); `.env` has `0600`. Only the bridge, built assets and public media belong under `public_html`.

Initialization and public importing completed on 2 November 2026. The initial import contained nine articles, 20 public author records, 15 editorial profiles and two application accounts: the suspended provenance identity and the initial administrator. The subsequent SJC imports described below brought the publication count to 48 and the active editorial board to seven. Existing SJC cron entries were preserved.

## Public content carried forward

The source snapshot includes nine published articles and nine original PDFs, 20 deduplicated author records, 15 editorial profiles, 19 journal pages, 38 raster images and eight forms/templates. The importer verifies file hashes and signatures before writing, retains original article metadata and PDFs, and maps 95 changed legacy URLs to their new local destinations. It does not copy old website accounts, unpublished submissions or private workflows. Repeated imports preserve subsequent editorial changes.

The original `/editor-guidelines` page returned HTTP 500 and could not be imported. The old external direct-payment link remains on the original website; no payment gateway was configured for the preview. Indexing claims were not inferred from unverified source links.

Long article URLs use the application's persisted canonical slug. Legacy abstract/PDF links resolve to that actual slug, including the longest source title. Editorial updates to a canonical slug are retained on a repeated import. New generated slugs are trimmed at a word separator so truncation cannot leave an invalid trailing hyphen.

Hostinger's CDN can recompress delivered images. The importer rechecked all original stored file hashes; the nine article PDFs and eight downloadable forms/templates are verified separately against the source bytes.

The embedded article reader uses matching compatibility builds of PDF.js and its worker. The standard build failed while processing original PDFs in runtimes missing recent JavaScript methods. Safari also lacks the stream async iteration used by PDF.js's `getTextContent()` helper, so the reader extracts the same text chunks through `getReader()/read()`. The regression tests render two different pages and verify actual text extraction with the missing APIs removed; extraction errors continue to propagate.

## Initial verification record — 2 November 2026

- PHP: 296 tests passed, with 3,299 assertions, in the isolated validation checkout. The image-optimization checks used the installed native optimizer and the suite used a 512 MB memory limit.
- JavaScript: all 12 tests passed, including four PDF-reader regressions. The production Vite build succeeded.
- Live PHP/MySQL: initialization and import completed against the separate preview database. Administrator sign-in and the settings, articles, editorial-directory and journal-page screens returned successfully; the verification session was signed out afterward.
- Live public checks: 39 pages, all 55 imported assets, all 95 changed legacy URLs and 16 built/static assets passed after connection reuse was enabled. Original article PDFs and forms match source hashes; CDN image copies were checked for matching content and proportions after resizing/recompression.
- Safari acceptance: the imported seven-page article completed rendering, moved from page 1 to page 2, and exposed the original page text without a reader error after the stream compatibility fix. All five final Vite build files were also checked against local release hashes.
- The initial preview used HTTPS with a valid certificate, noindex headers/meta, robots disallow, and a disabled sitemap. Sensitive configuration, vendor and private-file paths returned 403 or 404.
- The SJC site returned HTTP 200. The existing SJNR domain still resolved to its prior `207.174.214.35` address and GoDaddy nameservers; no final-domain DNS cutover was performed.

The local non-secret deployment reference is `.env.sjnr.example`. Generated credentials are stored separately in the private local handover, never in this document or Git. Existing deployment archives are initial-install artifacts and should not be reapplied over an initialized site's `.env` or storage. For future asset updates, replace only the built files while retaining `public_html/storage` and private application storage.

## Additional SJC public collection

At the owner's request, the public collection from `https://sjcjournal.com` is also copied into the preview: 39 articles, 39 PDF downloads and 38 cover images, retaining the existing nine SJNR articles. The resulting collection has 48 publications. Each copied article credits the original Singapore Journal of Cardiology publication and links to its source; the PDFs retain their original journal identity and copyright notices.

The collector is `scripts/import/sjc_public.py`; the target-specific CLI helper is `scripts/hostinger-import-sjc-public.php`. The helper validates the complete source inventory and file hashes before writing, refuses unrelated DOI/URL collisions, preserves existing accounts and records, and uses a private completion marker to prevent duplicate imports. A temporary HTTP-denied staging directory supplies public source files during deployment and is removed afterward. The import's permanent provenance and completion records remain in private application storage and database settings.

The live import completed with 39 new articles, 176 new author records and 38 covers; the article count rose from nine to 48. All 39 copied article pages returned successfully, all 39 PDF hashes matched the source downloads, and every source article appeared in the paginated listing. The temporary import cron was removed and its staging helper now returns HTTP 404. Email was paused at that import step; see the current configuration below.

## SJC editorial board and public information

The additional public-information copy reviewed 234 source URLs: 45 journal-page entries, seven editorial profiles, 176 contributor profiles, three resource PDFs, six categories and the source homepage selections. The preview now shows the seven SJC board members with their portraits, the source About content, two featured articles, five selected latest articles, source categories, public office address/phone/WhatsApp and three resource downloads. Contributor profiles on SJC contain names and publications but no additional biographies or portraits to transfer.

The source has no published body text on 44 policy entries. Eighteen existing SJNR guidance pages were retained where richer published content already existed; the remaining empty entries keep their unpublished states. SJNR identity, ISSN, search-indexing controls, email configuration, accounts and all 48 articles were retained. Previous board records remain inactive, and a private rollback snapshot was saved before changes in `journal_app/storage/app/private/deployment/before-sjc-pages-20261102-101806.json`.

`scripts/import/sjc_pages.py` collects this public snapshot; `scripts/hostinger-import-sjc-pages.php` applies the guarded import. The resource page and navigation now read the imported `journal.resources` setting before profile defaults. Verification passed for 57 public page requests, all seven board profiles/photos, all six source categories, both homepage featured selections, and byte-identical copies of all three source PDFs. Eight journal-profile feature tests passed with 92 assertions. The temporary import cron and HTTP-denied input directory were removed after success; the removed helper returns HTTP 404.

## Domain repair and final-domain release on 3 November 2026

Hostinger renamed the temporary installation to `sjnrjournal.com`. The initial domain change left old absolute paths in `.env` and the scheduler. Consequently the public layout could not find `build/manifest.json`, omitted Vite CSS/JavaScript, and generated image URLs for the retired temporary hostname. The built assets and article files were intact.

`scripts/hostinger-repair-sjnr-domain.php` corrected `APP_URL`, `SITEMAP_BASE_URL`, `APP_PUBLIC_PATH` and `PUBLIC_DISK_ROOT`, and cleared the configuration and compiled-view caches. The helper validates the exact installation, production SJNR profile and resolved MySQL database before making changes, and stores `.env`/cache/rewrite backups privately. It does not reset data or seed accounts. The copied collection remained at 48 articles and two accounts.

The scheduler now uses the final-domain path shown above; its previous broken cron was removed. The optional indexing phase enabled `SEARCH_INDEXING_ENABLED=true` and removed the preview-only `X-Robots-Tag` directive. Public robots and the sitemap are available again; account routes retain noindex. `.hostinger/sjnr-public.htaccess` contains the live rewrite configuration, including a 301 redirect from `www.sjnrjournal.com` to `sjnrjournal.com` that preserves paths and queries. Keep the original generic `public/.htaccess` for other journal installations.

Copied SJC papers now use their verified private import provenance for original journal/ISSN citation tags, displayed citations and structured data. The original nine SJNR papers keep nursing journal citations. Source PDF files, DOIs, publication dates and canonical slugs were not rewritten.

Verification on the live final domain passed for 66 public/account pages, all 48 article pages and original PDF hashes, seven board portraits, all three resource PDF hashes and five active build/worker files. A second pass checked correct scholarly metadata on all 39 copied SJC papers and nine original SJNR papers. No inspected page references the retired temporary origin. The isolated PHP suite passed 300 tests with 3,343 assertions; the standalone domain and mail deployment checks passed 12 and 39 fixtures respectively. The JavaScript suite passed all 12 tests. No local development server is required for those checks.

Private rollback directories include `journal_app/storage/app/private/deployment/before-sjnr-domain-20261103-060905-e900dbac`, `before-sjnr-citations-20261103-062304-ec6669c1`, and `before-sjnr-domain-20261103-063204-04dcb39c`. These contain private deployment configuration and must not be served or committed. Temporary repair helpers and their one-off cron entries were removed after verification.

## Hostinger email

The active mailbox is `notifications@sjnrjournal.com`. Its Starter Business Email free trial expires on 3 November 2027. Auto-renewal is disabled, so the plan requires an explicit later renewal to continue after the trial. Existing SJC subscriptions and cron entries were preserved.

GoDaddy remains authoritative for DNS. A zone export was saved before changes. The existing web A record (`89.116.133.47`), `www` alias, domain-connect record, nameservers and ownership TXT were preserved. The following mail records were added and verified at the authoritative nameserver:

| Type | Name | Value | Priority |
| --- | --- | --- | --- |
| MX | `@` | `mx1.hostinger.com` | 5 |
| MX | `@` | `mx2.hostinger.com` | 10 |
| TXT | `@` | `v=spf1 include:_spf.mail.hostinger.com ~all` | — |
| CNAME | `hostingermail-a._domainkey` | `hostingermail-a.dkim.mail.hostinger.com` | — |
| CNAME | `hostingermail-b._domainkey` | `hostingermail-b.dkim.mail.hostinger.com` | — |
| CNAME | `hostingermail-c._domainkey` | `hostingermail-c.dkim.mail.hostinger.com` | — |
| TXT | `_dmarc` | `v=DMARC1; p=none` | — |

GoDaddy returned a 3,600-second TTL for the new mail records. DNS caches may retain prior answers until they expire. Hostinger's mailbox dashboard reports the domain connection completed. DMARC currently uses `p=none`; it does not request rejection of failing mail.

The website uses SMTP over verified implicit TLS to `smtp.hostinger.com:465`, with the mailbox as both sender and office notification recipient. The password remains in private `.env` configuration outside Git. `scripts/hostinger-configure-sjnr-mail.php` accepts an authenticated encrypted deployment envelope, validates the target and SMTP credentials, writes only mail settings with a private backup, and prevents repeated test messages. It deliberately keeps mail paused until receipt has been verified.

The live server authenticated successfully and sent test reference `07bbaca918d73a91`. Hostinger’s inbound delivery log records `Delivered` with a `250 2.0.0 Saved` result. The subsequent empty Inbox/Trash had UID counters showing prior messages; receipt was confirmed from the provider log rather than current folder contents. Mail was activated on 3 November 2026 after another successful TLS/authentication check. At activation there were no queued mail/default jobs or failed jobs. No private manuscripts or account data were included in the test.

A second self-addressed check, `608ba1a51df66fee`, used the real queued notification class and the scheduled worker. It was queued at 07:11 UTC, processed with no remaining or failed mail jobs, and retrieved read-only from the Inbox. Its SPF, DKIM and DMARC authentication results all passed; the DKIM signature uses `sjnrjournal.com`. The HTML uses SJNR identity and contains no retired temporary-host URLs. Both tests target only `notifications@sjnrjournal.com`.

The second SJNR cron runs every minute:

```text
/opt/alt/php84/usr/bin/php /home/u709299470/domains/sjnrjournal.com/journal_app/scripts/hostinger-mail-worker.php
```

Future mailbox changes require updating private SMTP configuration and clearing Laravel's configuration cache. Use `scripts/hostinger-mail-worker.php` with PHP 8.4 for the queue; it refuses to process while `storage/framework/mail-paused` exists or credentials are absent. Preserve held queues and review failures before retrying messages. Never deploy an old initial-install archive over an initialized site's `.env` or storage.

All temporary domain/mail staging directories, the encrypted setup envelope and one-off cron entries were removed after completion. Only the SJNR scheduler and guarded mail worker remain for this installation; the two original SJC cron entries are unchanged. A private rollback copy for mail configuration is `journal_app/storage/app/private/deployment/before-sjnr-mail-20261103-064906-fae04de4`.

## Website administrator access

The administrator signs in at `https://sjnrjournal.com/admin/login` using `admin@sjnrjournal.com`. On 3 November 2026, the existing account's password was updated through the protected user-management form, preserving its permissions, profile and verified status. Fresh sign-in succeeded, and a pre-change session was redirected to the administrator sign-in page.

The current password is retained only in the private deployment handover outside source control. Keep password values out of journal profiles, example environment files and seed defaults. Temporary verification sessions were signed out after the update.
