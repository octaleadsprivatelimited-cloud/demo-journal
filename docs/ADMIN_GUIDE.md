# Journal administration guide

This guide covers the SJC Journal account, manuscript and homepage controls. The workflow has six main phases; the smaller stages record checks inside those phases.

## Find the right tool

The sidebar keeps Dashboard, Manuscripts, Articles and People in view. Website groups pages, disciplines and the public editorial board. More tools contains reports, media, messages and settings; it opens automatically when one of those pages is active. Controls appear only for roles allowed to use them.

The dashboard offers four quick actions and a collapsed six-step publishing guide. People separates Accounts from Account approvals, so pending registrations do not crowd the account list. The navigation and staff forms adapt to phones and tablets.

## Manuscript workflow

Open **Manuscript workflow → View** for the relevant article. Read **Current stage** and **What happens next**, then choose an action from **Available actions**. The **How the workflow works** panel explains the complete journey. Use the main tabs for overview, files, reviews and activity; additional editorial/production tabs are under More options.

| Step | Who acts | What they do |
| --- | --- | --- |
| 1. Submission | Author / Contributor | Complete author details, abstract, keywords and required manuscript/cover-letter files. Submit; receive a receipt. |
| 2. Editorial checks | Admin and Editor | Admin assigns an editor. Editor checks format/documents, records similarity evidence and screens suitability. Return for corrections or send for peer review. |
| 3. Peer review and decision | Editor, Reviewers and Author | Editor invites reviewers. Reviewers accept and submit reports. Editor requests minor/major revision, accepts or rejects. Author uploads revisions and a response; editor/reviewers recheck. |
| 4. Copyediting and proof | Editor and Author | Record acceptance and download its letter. Editor copyedits and sends a galley. Author requests corrections or approves the final proof. |
| 5. Metadata and DOI | Editor and Admin | Verify metadata, journal issue and final PDF. Generate the article number. Record DOI submission, registration and activation evidence. |
| 6. Publication and indexing | Admin | Publish the verified article, record external indexing results and archive the completed workflow. |

Return/revision steps are loops, not extra publication requirements for every article. Rejected and withdrawn submissions stop before publication. A completed workflow archive is separate from the article's public publication status.

The next action depends on the current stage and the signed-in role. Authors cannot perform editorial decisions; reviewers see assigned work; account, ownership and publication prerequisites remain enforced. Confidential reviewer comments are available to authorized editorial staff and do not appear in author emails.

DOI and indexing entries record evidence of work with external providers. The website does not submit to a DOI agency, plagiarism service or indexing database automatically.

## Add editors and reviewers

**Super Admins have two options:**

1. **People → Add editor / Add reviewer:** enter the name, email, password and confirmation. Professional details are optional. The account is approved by the Super Admin creating it; a verification link is queued automatically. The person must verify their email before signing in. Use More account tools → Add another role for other account types.
2. **People → Account approvals:** the person registers through the editor/reviewer registration page. The Super Admin approves the pending application. Portal access requires both approval and email verification, in either order.

Normal Admins cannot create or approve staff accounts. **Manuscript workflow → Editors & reviewers** manages existing staff profiles and availability; it links Super Admins to Add editor, Add reviewer and Account approvals.

**Website → Editorial board** manages public names, appointments, photos and display order. A board profile does not create a login or grant editorial access. To show a chief editor, use the Editorial board group, an Editor-in-Chief role label, Active status and the desired Sort order. All active chief editors appear on the board page. Both homepage editor groups rotate automatically every second without buttons or counters, returning to the first profile after the last. Rotation pauses while a profile has keyboard focus or pointer interaction, while the page is hidden, or when reduced motion is requested.

## Add or change disciplines

Use **Website → Categories / disciplines**. Categories are the website's **Disciplines & fields**.

- Choose New category to add a discipline, or Edit to rename/change an existing one.
- Set Parent category to Top level for a main discipline, enable Visible on the public journal and set Sort order.
- Add a description, optional image and SEO metadata as needed.
- Assign an article to its discipline through Articles → Edit → Category.
- Active top-level disciplines appear on the homepage even with zero articles. The homepage displays the first eight by sort order; All disciplines shows the complete directory.

The requested initial disciplines are Preventive Cardiology, Vascular Medicine, Interventional Cardiology, Cardiac Electrophysiology and Diagnostics. Existing Cardiology content keeps its current classification; no scientific subject assignment is inferred automatically. Empty Culture & Ideas and Climate & Environment categories can be retained as inactive records. Categories containing articles or child categories must be reorganized before the one-time setup retires them.

Renaming a category regenerates its slug and changes its public URL. Review links and SEO settings after renaming a discipline.

## Homepage selections and images

Open the relevant article in **Articles**, or open its workflow record, and use **Homepage hero → Featured → Save hero placement**. Latest adds it to the rotating latest selections; Hidden removes its hero placement. Only published, non-future articles are eligible for public display. Placement changes do not change the manuscript or publication status.

The hero includes featured/latest selections. Featured research displays the two most recently updated featured publications. New perspectives displays six recent publications in a balanced grid. The journal archive uses wider two-column cards with panoramic image areas and a single column on small screens.

The two requested selections are:

- Unveiling cardio-ankle vascular index: an independent predictor of hypertensive response to exercise.
- Prospective, non-interventional, observational study assessing the effectiveness of perindopril and perindopril/indapamide in a Bangladeshi population of patients with hypertension.

Social URLs remain editable under **More tools → General settings → Social**. The public footer renders recognizable icons with accessible names instead of visible network-name text.

## Communications

Account applications, verification, approval/rejection, manuscript receipts, reviewer invitations/reports, revisions, proofs and publication updates use the relevant notification flows. Contact submissions produce receipts and office alerts. Email delivery is queued, so a successful save confirms the application action rather than inbox delivery.

The sender is `notifications@sjcjournal.com`. The regular Hostinger worker processes new mail through cron; older held contact messages remain on their separate held queue. See [Mail setup](MAIL_SETUP.md) for operational details.

For implementation details, see [Architecture](ARCHITECTURE.md), [Tech Stack](TECH_STACK.md), [Workflow verification](WORKFLOW_VERIFICATION_20261008.md) and [Public profile photos](PROFILE_PHOTOS_20261009.md).
