# GA4 and SEO — 9 October 2026

Google account: larixitsolutions@gmail.com. Google Analytics account: Singapore Journal of Cardiology (411322128). GA4 property: SJC Journal — sjcjournal.com (558108739). Web stream: SJC Journal public website (16070892193). Measurement ID: G-VPMDF48T9K.

[Open Analytics](https://analytics.google.com/analytics/web/?authuser=2#/a411322128p558108739/reports/intelligenthome). Reporting time zone: Singapore (GMT+08:00); reporting currency: SGD. Optional account data-sharing settings were left off. Google signals and user-provided data collection were not enabled. Enhanced measurement was explicitly disabled in the saved web stream to prevent automatic form and search capture. The integration sends page views and published PDF downloads, along with GA4's standard session/engagement measurements. Safe campaign parameters are mapped to campaign fields while page URLs omit query strings. Referrers contain only the referring origin.

Microsoft Clarity setup was deferred at the user's request. It is not enabled and has no project ID on the website.

## Visitor choices and boundaries

Tracking is enabled only in production on sjcjournal.com, for anonymous visitors on a defined list of public reader pages. It never loads on account, registration, submission, editorial, reviewer or admin pages, for authenticated users, on contact/search pages, or when arbitrary query parameters are present. A valid identifier is required; local development does not send analytics.

Neither external tag loads until the visitor chooses Accept analytics. Necessary only keeps optional tracking off. Analytics preferences reopens the choice. Consent lasts six months in browser local storage. Withdrawal disables GA, clears its journal cookies and reloads the page to unload optional scripts. Advertising storage, advertising user data and ad personalization are denied. A factual analytics disclosure and Google privacy link are included on the privacy page. No manuscript, account identity or form contents are included in custom analytics events.

The CSP permits only the required tracking hosts on eligible public requests. Existing policies with only upgrade-insecure-requests are preserved without introducing accidental script restrictions. Private routes receive an X-Robots-Tag: noindex, nofollow header.

## SEO findings and changes

The predeployment crawl checked 48 pages, including all 39 published articles. All checked pages had titles and descriptions; 229 sitemap URLs use https://sjcjournal.com. The home canonical uses the valid equivalent root URL without a trailing slash. The update adds visible, factual homepage copy and WebSite schema topics: cardiology research, cardiovascular medicine, clinical case reports and cardiology reviews. It corrects member directory pagination canonicals and noindex handling for filtered member searches, links the privacy page in the footer, and expands robots exclusions for account and manuscript routes while preserving public author profiles.

The 39 imported articles lack original author keywords. Their titles, factual descriptions and scholarly citations are retained. Editors can add verified article keywords/focus keywords through the existing article form. The existing [keyword worksheet](SEO_KEYWORDS.csv) contains title-based editorial suggestions; these are not represented as original scientific keywords. Meta keywords are not added because Google does not use them for indexing or ranking.

## Validation and deployment

PHP feature coverage checks consent markup, exact identifier validation, production/host boundaries, query privacy, campaign mapping, CSP behavior, signed-in exclusion, page SEO, robots and pagination. All 238 application tests passed (2,795 assertions). All four JavaScript tests passed, covering no network before consent, acceptance without duplicate page views, withdrawal, expiration, sanitized referrers and PDF events. The isolated network-disabled deployment rehearsal passed, including rollback safeguards, worker restoration and idempotent replay.

Hostinger deployment uses a checksummed source patch and a self-restoring private CLI launcher invoked through the existing guarded worker cron. The launcher backs up changed source and the private environment, verifies the live baseline, preserves public storage, checks mail configuration, restores the regular worker and removes its temporary maintenance/mail hold. No test emails or journal notifications are sent. The 12 older held contact messages remain held.

Release analytics-seo-20261009 completed on Hostinger at 2026-10-08 19:07:03 UTC (9 October in India and Singapore), updating 11 source files. The production report confirmed the intended GA4 ID, production host, disabled Clarity, preserved public storage, configured SMTP, disabled development bypasses, one active verified Super Admin, zero active/failed jobs, 12 held older contact jobs and restoration of the regular mail worker.

Postdeployment HTTP checks verified the public GA4 configuration, query-free page URLs and safe campaign mapping; login, registration, contact, search and member-directory pages excluded the tag. Login/registration responses returned noindex, nofollow. The privacy notice and homepage topics were present. A second crawl checked all 48 pages and 229 sitemap URLs again, with no missing titles or descriptions and only the equivalent home root canonical difference.

Safari verification confirmed Necessary only hides the banner, Analytics preferences reopens it, and acceptance is remembered across reloads. GA4 Realtime receipt remains unverified: Safari's private-session privacy report explicitly showed googletagmanager.com under Blocked Trackers, and Realtime displayed zero users/events. Browser privacy protections were left enabled. Verify the first consenting visitor's page_view in Realtime from a browser that permits the Google tag before treating event collection as confirmed.

Evidence lives in ignored storage/app/deployment-evidence: analytics-seo-before-20261009.json, analytics-seo-after-20261009.json, analytics-full-suite-passed-20261009.log, analytics-javascript-20261009.log, analytics-rehearsal-final-20261009.log, analytics-production-20261009.json, analytics-production-http-20261009.json and analytics-safari-tracker-block-20261009.txt. These records contain no mailbox credentials.

## Implementation references

- [Google Analytics CSP requirements](https://developers.google.com/tag-platform/security/guides/csp)
- [Google supported metadata](https://developers.google.com/search/docs/crawling-indexing/special-tags)
- [Google Analytics privacy information](https://policies.google.com/technologies/partner-sites)
