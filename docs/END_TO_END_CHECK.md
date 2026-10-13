# End-to-end verification history

## 14 October 2026 — production inspection and assignment repair

The live site is `https://sjcjournal.com`. Safari authenticated the real Super Admin account and opened People, the manuscript list, a submission record, its workflow and reviewer assignment screen. Public author/editor/reviewer registration, login and verification resend pages responded successfully. Production has one verified Super Admin, one verified author and an active editor/reviewer whose email is still unverified. That staff account cannot use its workspace until verification is completed; the audit did not mark it verified or change its existing manuscript/review assignment.

The inspection exposed a missing verification check on new staff assignments. Commit `d165cf1` fixes workflow, legacy and article metadata assignment paths and hides unverified staff from new assignment choices. Six regression cases cover selectors, direct editor/reviewer requests, legacy actions, review reassignment and metadata creation. The relevant isolated test run passed **47 tests with 896 assertions**, including actual serialized database-queue notifications throughout the editorial journey. Tests used their own database, rather than the production journal database.

The eight runtime files were deployed on Hostinger at `2026-10-13T21:04:03Z` (14 October in India). Successful-release, repeated-launcher and rollback rehearsals preserved fixture records, mail and configuration. The private production completion report confirms every updated source checksum, the restored regular mail worker, unchanged private environment, and unchanged record counts: 5 users, 40 articles, 1 submission and 1 review. The live homepage and authenticated workflow pages rendered successfully after deployment. The 12 original contact notices remain held, 12 older failed mail notices remain held, 36 failed publication jobs remain retained, and 21 redundant pending publication jobs remain in their held maintenance queue. No historical messages were retried, deleted or pruned.

The earlier three labelled verification/approval/submission **template delivery tests** are present in the notifications inbox and carry DKIM signatures; Hostinger delivery logs show Delivered. Those emails created no real account or manuscript. They establish transport and template delivery, rather than a complete live registration-to-review journey.

**Fresh live transactional journey: pending.** The owner authorized three dedicated author/editor/reviewer email aliases into the notifications inbox, plus normal test office alerts. The aliases are created and Safari registration forms are prepared. Creating the passwords, accepting the terms and submitting these applications require the owner to complete the credential handoff. At this checkpoint there are no new test applications, no verification emails for these aliases and no test manuscript. Verification, Super Admin approval, role sign-ins, author submission and editor/reviewer notification links still require live transactional validation after registration. The test manuscript must remain unpublished, existing journal records must remain unchanged, and older queues must stay held.

## 4 September 2026 — archived local audit

The following results describe the local environment as inspected on that date; the historical production boundary below is not the current Hostinger deployment status.

## Verified locally

- Complete Laravel regression suite: 106 tests passed with 807 assertions after the autosave indicator changes. The final rich-text event adjustment also passed the real browser autosave check.
- Expanded publication regression test now performs workflow actions through authenticated HTTP requests, including submission, editorial checks, reviewer invitation and review, acceptance PDF, copyediting, author proof corrections, verified metadata, DOI recording, publication and archive visibility (84 assertions).
- Browser checks: 11 public pages at 1440, 694 and 390 pixels (33 page/viewport combinations); no page errors or horizontal overflow.
- Real browser sign-in and navigation: author (7 panel pages), admin (12), editor (9), reviewer (2). No HTTP 500 responses or JavaScript exceptions in this journey.
- Real author submission through all five wizard steps with multipart PDF uploads; admin editor assignment; assigned editor sees the manuscript and the next workflow action.
- Real draft save and autosave: detail changes save automatically; author-detail changes retain a manual-save warning even after a later automatic save.
- Application database responds, all migrations are applied, private/public storage directories are writable, and the running database has zero queued or failed jobs at the time checked. Web, PostgreSQL, Redis and Mailpit report healthy; application, queue and scheduler are running.

## Changes made during this check

Added the missing visible autosave status. Automatic saving now distinguishes article details from fields that require a manual save, so an automatic save cannot incorrectly clear the warning for unsaved author details. Declarations must be confirmed when submitting. Expanded the publication regression test to use the HTTP request pipeline.

Browser test users and manuscripts were created only in a temporary SQLite website with an explicit startup isolation guard. No QA articles or accounts were added to the real journal database.

## Production boundary

Local email is routed to Mailpit; external inbox delivery has not been verified. Google sign-in credentials are not configured. DOI registration/indexing use the configured manual workflow and require real agency/service confirmation. Remote hosting, HTTPS, production credentials, backups and external delivery need validation on the actual production environment.

Passing these checks establishes the tested behavior, not a guarantee against every future software or infrastructure error. Run `docker compose run --rm --no-deps test php artisan test` for future regression checks, never against the journal's live database.
