# End-to-end verification — 4 September 2026

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
