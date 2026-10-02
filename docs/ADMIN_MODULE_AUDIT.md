# Admin and website verification — 10 September 2026

## Fixes
- PostgreSQL article search now matches partial titles (for example, `irrig` finds `Smarter Irrigation`) as well as full-text terms.
- Admin people, tags, media, comments, enquiries, and newsletter searches now use case-insensitive matching.
- Article filters have a Reset filters link.

## Verification
- Full isolated SQLite regression suite: 130 tests passed, 1,106 assertions.
- Added checks for 18 admin landing/create pages and six public pages.
- Added assertions for combined article search/status/category filters, active versus trash separation, and empty search results.
- Browser checks against the running PostgreSQL app: partial article search, draft filter, combined published/category filter, reset link, mobile navigation expansion and submissions link, and pending submission filter.
- Existing regression coverage includes author declarations, reviewer assignment, review completion, revision, approval, publication visibility, file replacement/deletion, settings, authorization, contact/newsletter persistence, moderation, and notification dispatch.

## Limits
This is not a claim that every possible input or external integration was verified. Live email delivery, external DOI registration and indexing were not exercised. Workflow mutation checks used isolated data; existing user articles were not modified or deleted during this audit.
