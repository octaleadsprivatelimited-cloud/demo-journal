# Google login and signup

Google One Tap and the standard Google Identity Services button share one server-side flow for author, contributor, reviewer, editor and admin workspaces. First-time users become pending applicants; returning approved users enter only an assigned role. Super-admin privileges cannot be requested through signup. Contributor applicants also receive an inactive author profile for manuscript work.

## Activation

Set these privately in the deployment environment:

```
GOOGLE_OAUTH_ENABLED=true
GOOGLE_CLIENT_ID=<Web application client ID>
GOOGLE_CLIENT_SECRET=<client secret>
GOOGLE_ONLY_AUTH=true
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback
```

In Google Cloud's Web application OAuth client, register `http://localhost:8080` as an authorized JavaScript origin and `http://localhost:8080/auth/google/callback` as an exact authorized redirect URI. For production use the actual HTTPS origin and matching callback URI. Configure the OAuth consent screen and any required test-user list. Never commit the client secret.

Before switching to Google-only mode, ensure an existing active super-admin has a matching Gmail or Google Workspace email or an already verified Google identity link. Otherwise new applicants cannot be approved. Existing non-Google-hosted email identities need administrator-assisted linking after identity verification. Merely selecting a workspace never grants its permissions.

When credentials are missing or Google is disabled, existing password access remains available to avoid locking out administrators. Once enabled, `GOOGLE_ONLY_AUTH=true` removes password fields from all five role signup/login pages and rejects password login, signup, reset, confirmation and password API-token creation. `GOOGLE_ONLY_AUTH=false` allows a staged migration with both methods. Existing passwords are not deleted.

Rebuild the application with the updated Composer lockfile, update all application/queue/scheduler containers, and clear/rebuild cached configuration according to the deployment procedure. The localhost environment has no Google credentials, so real Google-account login cannot be verified yet.

## Security and behavior

- Browser credentials post through the same-origin Laravel form with CSRF protection and a single-use, ten-minute session nonce bound to the selected portal.
- Google ID tokens are verified using `firebase/php-jwt`, Google's signing keys, audience, issuer, expiry and verified-email claims. Signing keys respect a bounded cache lifetime and refresh once on verification failure.
- Existing accounts are located by immutable Google subject ID. Email-based automatic linking is limited to Gmail or Google Workspace identities for which Google is authoritative; third-party email matches require verified linking.
- Approved roles and active-account checks apply to both OAuth redirects and One Tap. An admin applicant receives no role until approved. Disabled author registration is respected.
- Google's official button and One Tap script are permitted by CSP; popup-compatible opener headers are used when Google is enabled. A Google OAuth redirect remains available if One Tap is suppressed or the script cannot load.
- One Tap appearance depends on the browser, Google session, consent and Google cooldown rules. The Google button remains the fallback. The site does not ask for a password in Google-only mode, though Google may require authentication in its own account UI.

## Validation

Automated tests use locally signed test tokens and mocked Google keys, not live Google credentials. Coverage includes all role screens, pending admin signup, contributor login, incorrect role, expired token, wrong audience/issuer/nonce, unverified email, forged signature, missing session, unsafe third-party email linking and disabled-provider fallback.

Official references: https://developers.google.com/identity/gsi/web/guides/verify-google-id-token and https://developers.google.com/identity/gsi/web/reference/js-reference
