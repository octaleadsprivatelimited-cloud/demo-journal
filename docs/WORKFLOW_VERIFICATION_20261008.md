# Editorial journey verification — 8 October 2026

## Verified journey

Registration → email verification → Super Admin approval → login → author submission → editor assignment → document and similarity checks → reviewer invitation and acceptance → review completion → major revision → resubmission → editorial recheck and acceptance → acceptance letter → copyediting and galley proof → author proof approval → metadata and issue assignment → publication.

Every role requires both verified email and Super Admin approval. Tests confirm that verification alone cannot grant access. Private reviewer comments remain hidden from authors in both the manuscript page and notification email.

## Fixes

- Removed duplicate editorial submission/revision alerts and duplicate author publication messages.
- Added manuscript title, ID, stage and applicable deadline to workflow email, with direct manuscript/review links. Reviewer invitations include the editor message and invitation deadline.
- Replaced decision-email links to the locked editing form with the manuscript workspace. Published manuscripts expose a public-article link.
- Routed workflow notifications through the mail queue; both regular queue names remain supported by the Hostinger worker.
- Scoped editor counts to assigned manuscripts. Corrected Super Admin links for unassigned work and manuscripts awaiting decisions, and included legacy drafts in stage filtering.
- Required abstract, keywords, manuscript and cover letter in the submission wizard. Revisions require a new manuscript and reviewer response. Draft saving remains available without completing every field.

## Evidence

Full isolated regression suite: **220 tests passed, 2,515 assertions**. `EditorialJourneyDeliveryTest` processes real serialized database jobs and captures rendered Symfony mail, rather than faking notifications or the queue. It follows notification links with the appropriate role, checks recipient counts, exercises rejection and invalid submissions, and confirms that deadline reminders do not repeat.

Deployment rehearsal verifies all 17 source-file hashes, private source backups, application boot and database connectivity, production public path, disabled local bypasses, SMTP configuration, automatic restoration of the regular worker, cleared maintenance/mail holds, and safe replay. No schema migration is required by this patch.

## Scope

Automated submissions and reviews use isolated databases and storage. Mail rendering and queue delivery are tested locally; external SMTP delivery was confirmed separately during mailbox setup. This audit does not send test manuscripts or editorial updates to production users, and does not certify delivery to every recipient provider, external DOI registration or indexing acceptance.

Production verification is recorded privately under `storage/app/deployment-evidence`; credentials, submitted manuscripts and private deployment payloads are excluded from Git. The twelve historical contact-form messages remain held as requested.
