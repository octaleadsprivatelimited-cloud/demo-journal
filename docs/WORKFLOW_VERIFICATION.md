# Workflow verification — 10 September 2026

## Changes

- Local portal identity now carries into shared workflow routes while retaining the local-environment, enabled-flag, loopback-host, bind-address, and allowed-connection checks.
- Workflow-managed article and review pages link to workflow invitations rather than presenting incompatible legacy assignment/decision forms.
- Legacy reviewer assignment rejects inactive/unavailable reviewers, self-review, and duplicate current-round assignments.
- Author wizard validates earlier required fields before forward navigation. All five declarations are required before advancing beyond declarations.
- Submission request validation rejects each omitted declaration before article creation; the workflow service independently enforces declarations for submissions and revisions, including API submissions.
- Legacy author submission link now opens the complete submission form instead of an incomplete confirmation form.
- Declaration wording covers originality and permissions, exclusive submission, author approval, ethics/consent applicability, and competing interests/funding.

## Verification

Full isolated SQLite test suite: **125 tests passed, 1,043 assertions**. Includes the existing end-to-end HTTP workflow test through submission, editorial checks, reviewer invitation, review, acceptance, production, publication and indexing; revision and authorization regression tests; notification tests; and image optimization tests.

Browser checks: skipping required fields is blocked; unchecked declarations keep the author on step 4; all declarations allow step 5. No manuscript was submitted during the browser check.

Local HTTP check: admin dashboard followed by shared workflow returns 200 rather than redirecting to login.

## Image handling

Existing middleware processes nested uploads automatically. JPEG and PNG use lossless optimization; tests verify decoded pixels, dimensions, transparency and JPEG metadata. Valid SVG vectors remain intact; unsupported or unsafe image content is rejected. The existing limits are 5 MB input and 1 MB output. An image that cannot meet the output cap losslessly is rejected rather than resized or degraded. Compression savings depend on the original file; a smaller output cannot be guaranteed for every image without quality changes.

## Scope

Tests use isolated storage/database and fake external notifications. They do not certify live email delivery, external DOI registration, indexing acceptance, or journal accreditation. Publication ethics wording was reviewed against COPE guidance; software checks cannot establish the truth of authors' declarations or replace editorial review.

References: https://publicationethics.org/files/editable-bean/COPE_Core_Practices_0.pdf and https://doi.org/10.24318/cope.2019.1.12
