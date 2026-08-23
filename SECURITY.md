# Security policy

## Reporting a vulnerability

Report suspected vulnerabilities privately to the repository owner or the security contact configured for the deployment. Do not open a public issue containing exploit details, personal data, credentials, or unpublished manuscripts. Include the affected route or component, impact, reproduction steps, and any suggested mitigation. The operator should acknowledge, triage, remediate, and coordinate disclosure according to its incident policy.

## Supported releases

Only the currently deployed release and the latest main-branch release candidate are supported. Production operators must keep PHP, Laravel, Composer packages, Node packages, PostgreSQL, Redis, Nginx, and container base images within their upstream security support windows.

## Security properties

- Every administrative, editorial, reviewer, and author mutation requires authentication plus server-side role and record authorization.
- Reviewers cannot alter manuscript content; authors cannot publish or assign reviewers; suspended accounts cannot use protected portals.
- Uploaded content is validated by MIME type, extension, size, ownership, and storage visibility. Executable uploads are not served from the application origin.
- Credentials and provider secrets are runtime-only. No private value may be exposed through a `VITE_` variable.
- Blade escapes ordinary output. Rich content must be sanitized before storage or rendering.
- CSRF, session rotation, login throttling, secure cookies, security headers, and audit records protect browser workflows.
- Production errors do not expose stack traces or configuration.

## Operator responsibilities

Use TLS, a unique `APP_KEY`, least-privilege database and object-storage identities, encrypted backups, MFA for infrastructure, restricted administrator accounts, central logging, dependency monitoring, and tested incident and restore procedures. Rotate any credential immediately if it may have entered a log, browser asset, build artifact, or repository history.

The optional local administrator bypass is a development convenience only. It requires `APP_ENV=local`, an explicit opt-in flag, a loopback-bound service, an `/admin` request, a loopback host, and an allow-listed raw connection address. Its marked identity receives in-memory privileges for one request only and is persistently inactive, unverified, roleless, and sessionless. Production, externally bound, and non-loopback requests always use normal authentication.
