# SJC Journal readiness implementation report

## Existing features

- Laravel 13 / Blade / Tailwind application with PostgreSQL, Redis, Sanctum, Docker, queues, scheduling, S3-compatible storage, and responsive public/admin layouts.
- Account registration, approval, authentication, email verification, and granular role/permission policies for Super Admin, Admin, Editor, Reviewer, Author, and User.
- Author drafts, autosave, co-authors, manuscript/supporting uploads, submission rounds, revisions, reviewer feedback, and status tracking.
- Reviewer assignments, deadlines, manuscript download, confidential comments, recommendations, completion history, and notifications.
- Editorial assignment, review tracking, decisions, scheduling, publishing, archiving, version history, and audit logs.
- Public articles, authors, categories, search, PDF/print access, contact, newsletter, sitemap, robots, canonical/Open Graph/Schema.org metadata, and configurable site settings.

## Newly implemented

- Admin-editable journal abbreviation, ISSN/eISSN, publisher identity/address/country, subject areas, publication frequency, and publication history. Unknown credentials remain blank and labelled `CLIENT INPUT REQUIRED`.
- Admin-editable public pages for Aims & Scope, Peer Review, Publication Ethics, Author Guidelines, Copyright, Open Access, Fees/APC, Indexing & Abstracting, Archiving, Privacy, and Terms.
- Transparent public fallback when a policy has not been supplied; no indexing, fee, or policy claims are fabricated.
- Backward-compatible migration for volume, issue, article/page number, received/revised/accepted dates, license, copyright, and correction/retraction/expression-of-concern status.
- DOI, public article ID, and article-number search coverage.
- Google Scholar/Highwire citation tags, Dublin Core identifiers, and richer Schema.org volume/issue data on published article pages.
- Normalized volume and issue records, article-to-issue assignment, public archive browsing, issue pages, and year filtering.
- Dedicated structured records for editorial board/reviewers/advisors/publisher staff and verified indexing services.
- Distinct Contributor role, registration, login, bounded manuscript permissions, and approval flow.
- Explicit reviewer assignment acceptance/decline, conflict-of-interest declaration, response notes, and private review-file upload.
- Admin-managed workflow email-template records and a readiness-record hub linked from Settings.
- Repaired Docker build ordering and secure runtime environment handling for explicitly seeded administrators.

## Partially implemented

- Existing queued workflow notifications cover core events and templates are editable records; wiring every legacy notification class to template substitution and adding scheduled reminder commands remains future work.
- The existing workflow maps several checklist labels into broader states (for example Approved/Scheduled) rather than exposing every checklist label as a distinct persisted state.
- The Contributor role is not currently seeded as a distinct role; the existing Author workflow is the closest bounded permission set.

## Client input required

- Official journal name/abbreviation, ISSN/eISSN, publisher legal name/address/country, publication frequency/history, subject areas, and logo authorization.
- Verified editorial-board, reviewer, advisor, and administrative staff records and ORCID identifiers.
- Approved journal policies, fees/waivers/discounts, copyright/license wording, preservation arrangements, and complaints/appeals process.
- Verified DOI prefix/registration process and any legitimate indexing status. No Scopus or UGC claim has been added.

## Verification note

- Docker/Vite production build passed.
- Authentication regression suite: 16 tests, 199 assertions passed.
- Full application suite initially passed 69 of 70 tests; the one failure exposed the existing secure admin-seeder environment lookup. After repair, its focused suite passed 4 tests and 29 assertions.
- Run `docker compose run --rm --build test`, `vendor/bin/pint --test`, and `php artisan migrate` once more in the deployment checkout before release.
