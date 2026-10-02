# Client page checklist implementation

Source: SJC.xlsx, Sheet1!A1:B50. The workbook specifies page titles, not approved policy text or journal facts. Sheet1!B53 references BMJ; direct browsing was blocked (403), and no BMJ wording was copied.

## Navigation and management

- Footer: a separate Policies & Peer Review section, with six expandable groups and an all-pages directory.
- Admin: Pages & policies → Website pages. Draft changes remain private, publication requires confirmation, and unpublishing retains the draft.
- Existing policy content remains available; existing routes are preserved.
- Corrections archive reads published articles with correction/retraction/expression-of-concern status. Manage that status through the article editor.
- Complaints, research ethics, fees/waivers and privacy enquiries use the existing contact workflow.
- Current issue resolves to the current populated issue, otherwise the archive.
- Unconfirmed wording, fees, identifiers, indexing and preservation claims are not invented.

## Checklist coverage

| # | Requested page | Destination | Management |
|---|---|---|---|
| 1 | Home | / | Existing feature |
| 2 | About the Journal | /about | Existing feature |
| 3 | Aims & Scope | /policies/aims-scope | /admin/pages/aims-scope/edit |
| 4 | Editorial Board | /editorial-board | Existing feature |
| 5 | Editorial Team / Editorial Structure | /policies/editorial-team | /admin/pages/editorial-team/edit |
| 6 | Peer Review Process | /policies/peer-review | /admin/pages/peer-review/edit |
| 7 | Publication Ethics & Malpractice Statement | /policies/publication-ethics | /admin/pages/publication-ethics/edit |
| 8 | Plagiarism Policy | /policies/plagiarism | /admin/pages/plagiarism/edit |
| 9 | Research Misconduct Policy | /policies/research-misconduct | /admin/pages/research-misconduct/edit |
| 10 | Authorship & Contributorship | /policies/authorship | /admin/pages/authorship/edit |
| 11 | Conflict of Interest / Competing Interests | /policies/competing-interests | /admin/pages/competing-interests/edit |
| 12 | Research Ethics / Ethical Oversight | /policies/ethical-oversight | /admin/pages/ethical-oversight/edit |
| 13 | Informed Consent Policy | /policies/informed-consent | /admin/pages/informed-consent/edit |
| 14 | Human & Animal Research Ethics | /policies/human-animal-research | /admin/pages/human-animal-research/edit |
| 15 | Data Sharing & Reproducibility Policy | /policies/data-sharing | /admin/pages/data-sharing/edit |
| 16 | Intellectual Property / Copyright Policy | /policies/copyright | /admin/pages/copyright/edit |
| 17 | Licensing Policy | /policies/licensing | /admin/pages/licensing/edit |
| 18 | Corrections & Retractions | /policies/corrections-retractions | /admin/pages/corrections-retractions/edit |
| 19 | Complaints & Appeals | /policies/complaints-appeals | /admin/pages/complaints-appeals/edit |
| 20 | Post-Publication Discussions / Comments | /policies/post-publication-discussion | /admin/pages/post-publication-discussion/edit |
| 21 | Generative AI / AI Policy | /policies/generative-ai | /admin/pages/generative-ai/edit |
| 22 | Open Access Policy | /policies/open-access | /admin/pages/open-access/edit |
| 23 | Article Processing Charges (APC) | /policies/fees | /admin/pages/fees/edit |
| 24 | Waiver / Discount Policy | /policies/waivers | /admin/pages/waivers/edit |
| 25 | Archiving Policy | /policies/archiving | /admin/pages/archiving/edit |
| 26 | Digital Preservation Policy | /policies/digital-preservation | /admin/pages/digital-preservation/edit |
| 27 | Publication Frequency | /policies/publication-frequency | /admin/pages/publication-frequency/edit |
| 28 | Journal History | /policies/journal-history | /admin/pages/journal-history/edit |
| 29 | ISSN / eISSN Information | /policies/issn | /admin/pages/issn/edit |
| 30 | Indexing & Abstracting | /policies/indexing | /admin/pages/indexing/edit |
| 31 | Abstracting / Database Coverage | /policies/database-coverage | /admin/pages/database-coverage/edit |
| 32 | Author Guidelines / Instructions for Authors | /policies/author-guidelines | /admin/pages/author-guidelines/edit |
| 33 | Manuscript Submission | /policies/manuscript-submission | /admin/pages/manuscript-submission/edit |
| 34 | Article Types | /policies/article-types | /admin/pages/article-types/edit |
| 35 | Manuscript Preparation Guidelines | /policies/manuscript-preparation | /admin/pages/manuscript-preparation/edit |
| 36 | Reference / Citation Style | /policies/reference-style | /admin/pages/reference-style/edit |
| 37 | Publication Charges | /policies/publication-charges | /admin/pages/publication-charges/edit |
| 38 | Reviewers / Reviewer Guidelines | /policies/reviewer-guidelines | /admin/pages/reviewer-guidelines/edit |
| 39 | Reviewer Code of Conduct | /policies/reviewer-conduct | /admin/pages/reviewer-conduct/edit |
| 40 | Editorial Policies | /policies/editorial-policies | /admin/pages/editorial-policies/edit |
| 41 | Publisher Information | /policies/publisher | /admin/pages/publisher/edit |
| 42 | Contact Us | /contact | Existing feature |
| 43 | Privacy Policy | /policies/privacy | /admin/pages/privacy/edit |
| 44 | Terms & Conditions | /policies/terms | /admin/pages/terms/edit |
| 45 | Copyright Notice | /policies/copyright-notice | /admin/pages/copyright-notice/edit |
| 46 | Site Disclaimer | /policies/disclaimer | /admin/pages/disclaimer/edit |
| 47 | Advertising Policy | /policies/advertising | /admin/pages/advertising/edit |
| 48 | Sponsorship Policy | /policies/sponsorship | /admin/pages/sponsorship/edit |
| 49 | Corrections / Retractions Archive | /corrections-retractions | Existing feature |
| 50 | Current Issue / Archives | /current-issue | Existing feature |

## Verification

- 15 focused tests passed (232 assertions): all 44 editable page routes, directory, existing public features, verified indexing, draft privacy, publish/unpublish, stale edits, authorisation, HTML sanitisation and corrections filters.
- Desktop and 390px mobile directory previews checked; footer peer-review expansion checked; admin draft editor checked.
- Azure image: `journal-azure:client-pages-20260911`. No database migration or content replacement is required. Page drafts are saved into the existing settings store on first edit.
