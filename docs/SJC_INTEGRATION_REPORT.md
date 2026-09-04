# SJC manuscript workflow integration

Implemented in the existing local Laravel application on 4 September 2026. The existing public website, authentication, role management, articles, submissions, reviews, versions, archives and publication URLs are reused. This checkout has not been deployed to sjcjournal.com.

## Entry points

- `/workflow`: role-scoped manuscripts, stage filters, summary counts and CSV reports. Existing author, editor and admin dashboards link here.
- `/author/articles/create`: five-step submission with author affiliations, corresponding author, declarations, manuscript, cover letter and up to ten supplementary documents.
- Existing `/reviewer` workspace: invitations, accept/decline, version-specific downloads, recommendations and completed-review locking.
- `/workflow/staff/directory`: reviewer/editor profiles and availability; administrators can update staff status and profile data. Account creation and role administration remain in existing user management.
- `/workflow/settings/configuration`: super-admin identifier formats, default deadlines, notification introduction and DOI agency settings.

## Lifecycle

Submission → initial document/format checklist → similarity assessment → editorial screening → reviewer invitation and peer review → editorial decision or revision/recheck → acceptance → copyediting → author proof/corrections → metadata verification → article number → manual DOI preparation/submission/registration/activation → existing publication → indexing and workflow archival.

State-specific actions are enforced transactionally in `ManuscriptWorkflowService`. Publishing requires acceptance, author proof approval, verified metadata, a final PDF, an article number and an explicitly confirmed active DOI. Editorial decisions are made by staff rather than reviewer voting. Indexing/archival does not remove published articles from the website.

## Data and access

The additive migration reuses existing `articles`, `article_authors`, `submissions`, `article_versions`, `reviews`, `users`, `roles`, `settings`, `notifications`, journal issues and indexing services. New tables hold workflow state, private immutable file references, activity, sequence counters and reminder deduplication. Review invitation fields, author affiliations and reviewer profile data extend existing records.

Authors access their own manuscripts, reviewers their assigned rounds, editors their assigned editorial records, and admins all manuscripts. Confidential comments, similarity reports and internal activity are excluded from author access on the server. Submitted versions and files are retained. Reviewer reports can only be changed after an audited reopening. Normal legacy action endpoints cannot bypass the new workflow once a manuscript is enrolled. Author API submissions use the same validation and transition engine.

Manuscript IDs and article numbers use separately locked sequence counters. Existing article numbers are checked when allocating new numbers. Existing records are not automatically converted: administrators can adopt an existing record with a recorded reason. Unpublished legacy records restart at checks; published records retain publication visibility and existing identifiers.

## Operations

The hourly `workflow:remind` command sends due-soon and overdue notifications for invitations, reviews, revisions and proofs, with per-deadline deduplication. Existing queue and scheduler services must remain running. Local mail is captured in Mailpit at `http://localhost:8025`; real email delivery requires the deployment's SMTP configuration.

Acceptance letters are rendered through Dompdf using the accepted metadata snapshot and journal logo. DOI handling is intentionally manual: preparation stores provider-neutral metadata through the `DoiProvider` interface. Registration and activation require staff evidence and confirmation; no Crossref network calls or credentials are configured. Indexing likewise records externally verified submissions/results rather than submitting to external services.

## Local verification and deployment

A local database backup was created before migration at `storage/app/backups/pre-workflow-20260904.dump`. It is ignored by Git and Docker build context. The new migration does not rewrite existing article records. Tests run only in the isolated SQLite test service, never against local journal content.

Regression coverage includes the full submission-to-publication/indexing lifecycle, forbidden transitions and roles, private documents/comments, revision file retention, review reopening, idempotent reminders, reports, API submission validation and existing public article/archive behavior. The complete suite passed 85 tests (604 assertions). A sample acceptance letter was rendered and visually inspected. The additive local migration completed successfully; the count and checksum of all 50 existing articles, slugs, DOI values, article numbers and publication statuses matched before and after. Production deployment and external DOI registration are separate operations and have not been performed.

For an existing installation, back up database and file storage, install locked Composer/npm dependencies, compile assets, run `php artisan migrate`, and restart the application, queue worker and scheduler. Do not run fresh migrations or reseed an existing database.
