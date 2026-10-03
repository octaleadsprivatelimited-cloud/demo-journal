# Journal security and workflow verification

## Repairs deployed

Registration confirmation now renders successfully after application submission. Signed verification links survive password and Google login only for the matching account and same application origin. Google cannot verify a changed local email through an older linked identity.

Password changes and resets revoke previous API tokens, remembered logins and other sessions. Ordinary web requests validate the current password hash. Local development bypass identities remain request scoped, and all four bypasses are disabled in production.

Co-author input accepts only documented fields. Conflicting ORCID values produce validation errors instead of database failures, and submitted profile fields cannot overwrite another account. Legacy publication transitions cannot skip the managed manuscript workflow. Draft submission uses the complete wizard, and reviewer acceptance updates manuscript and review-round states.

Production verification resend reports unavailable delivery when the mail driver only logs messages. Successful requests report queued delivery, without claiming inbox delivery.

## Validation

- 206 automated tests passed with 2,081 assertions using isolated databases and patched dependencies.
- Coverage includes registration and approval boundaries, account verification and recovery, co-author validation, multipart manuscript submission, private document access, reviewer acceptance and managed publication gates.
- Changed PHP files pass formatting checks. Compiled Blade templates pass PHP syntax checks. Production image and frontend build succeeded.
- Laravel upgraded from 13.26.1 to 13.34.0; CommonMark from 2.10.0 to 2.10.3.
- Composer and npm audits reported zero known dependency advisories for the checked locks.
- A private CLI deployment was rehearsed twice with the existing Hostinger public-path layout, isolated SQLite and no network. Source and dependency backups were retained outside the public directory, with checked rollback operations.

## Production verification

The Hostinger release `journal-security-20261003` completed at 2026-10-03 19:44 UTC. It reports Laravel 13.34.0, CommonMark 2.10.3, production mode, debug disabled, secure database sessions, database queues, disabled local bypasses and the preserved public HTML root. Database health passed; pending and failed job counts were both zero. The temporary deployment cron was removed; scheduler and queue-worker jobs remain configured.

The registration confirmation and administrator sign-in pages render in Chrome. The confirmation page produced no browser errors or warnings. A read-only production smoke check made 28 GET/HEAD requests with no 5xx responses: home, health, receipt and login pages returned 200; protected dashboards redirected guests to the appropriate login; the API returned 401. Environment/Git paths returned 403, and SQLite/private manuscript paths returned 404. HTTPS validated normally; session cookies carried Secure, HttpOnly and SameSite=Lax, and security headers included HSTS, nosniff, frame protection, referrer policy and permissions policy.

## Outstanding production setup

The deployed mail driver is `log`, with no SMTP credentials configured. Approval, verification, recovery and workflow notifications cannot reach inboxes until the operator privately configures the existing mailbox and validates SMTP authentication and delivery.

The readiness query found zero active, email-verified super-admin accounts. An intended journal administrator must be identified and securely provisioned or verified before application approvals can be completed in production. No accounts, credentials or verification states were changed by this audit.

These checks validate the tested workflows and known dependency advisories. They are not a guarantee that every possible defect or vulnerability is absent. No production application, manuscript or test email was created.
