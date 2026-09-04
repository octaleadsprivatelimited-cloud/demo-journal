# Upload and update recovery

## Guardrails

- Local, public and S3 storage throw on failed writes. Controllers cannot silently save a `false` upload path. Storage failures are logged by Laravel and return a safe 503 HTML or JSON response. Workflow transaction tests verify that a failed upload does not advance the stage or create file records.
- Browser forms check the PHP/Nginx limits before submission: up to 20 files, 5 MB per original image and 20 MB per document in browser checks (PHP has a 24 MB per-file ceiling), and 32 MB for the complete request (with multipart overhead reserved). Existing field validation remains stricter where required: documents 20 MB, compressed images 1,000,000 bytes. These checks do not replace server validation.
- Nginx and Laravel return actionable 413 responses. API paths receive JSON. Large supplementary submissions must be split into smaller batches.
- Image processing retains lossless compression, validation and the 40-megapixel ceiling. Each compression process has at most 15 seconds, with a shared 20-second request processing budget to prevent multi-image batches exhausting the PHP execution window.
- Error pages use standalone HTML and inline CSS, with no database, session-dependent public layout or Vite manifest required.
- Missing homepage and article-card images use the editorial placeholder once. The fallback does not retry endlessly if the placeholder is also unavailable.
- Autosave retains the unsaved-change warning after network failure and while saving. Requests time out after 20 seconds. A version-conflict response stops further autosave attempts until the page is reloaded; copy unsaved text before reloading. Selected files are not included in JSON autosave and keep the navigation warning active until a normal form submission.
- Nginx resolves the application through Docker DNS periodically, avoiding a permanently stale backend IP after container replacement. Missing static assets return 404 instead of being routed into an application page.

## Verification and future releases

Run `docker compose run --rm --no-deps test php artisan test` in the isolated test service. Never run tests against the live journal database. Run `docker compose run --rm --no-deps web nginx -t` before applying web-server configuration changes. Build application and web assets from the same revision, run required migrations, then update the application, web, queue and scheduler services together.

Inspect application logs and disk capacity when storage returns 503. Keep original uploads and verify the saved article state before retrying. Keep backups and a tested restore procedure for database and uploaded files. Production should use `APP_DEBUG=false`.

These protections cover tested failure cases, not every possible infrastructure failure. Uploaded files are not browser autosaves; unsaved text remains only in the open page. Database transactions do not undo files already written before a later operation fails, so storage cleanup and monitoring remain operational responsibilities.
