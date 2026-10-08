# Journal email setup

Form enquiries and manuscript alerts go to `CONTACT_NOTIFICATION_EMAIL` as well as active staff recipients. Account application and decision alerts go to `ACCOUNT_NOTIFICATION_EMAIL` and active Super Admins. A staff account using the office inbox receives one copy. Contact submitters and account applicants receive receipts; applicants receive approval or rejection decisions. Existing manuscript, review, proof, password recovery and newsletter notifications use the same configured mailer. New notifications and verification messages run after commit on the `mail` queue.

Password registrations queue email verification immediately, before approval. The signed signup link confirms the current address without logging in, activating the account, or granting a role. It expires after 60 minutes by default and is invalid after the account email changes. Every role still requires Super Admin approval. Pending applicants can request another link at `/registration/verification`; responses do not disclose whether an address has an application, and requests are throttled. Google applications with a verified identity do not receive duplicate verification messages. Approval sends a new verification link only if the email is still unverified.

## Current Hostinger deployment

The production sender is the Hostinger mailbox `notifications@sjcjournal.com`; the editorial recipient remains `info@larixjournals.com`. Domain DNS is managed in GoDaddy. On 8 October 2026, SMTP authentication succeeded from Hostinger and a single authorized test email arrived in the sender mailbox inbox. Production uses authenticated SMTP over TLS, with credentials stored only in the private server environment.

On 8 October 2026, Hostinger accepted domain ownership verification. GoDaddy's authoritative DNS serves the two Hostinger MX records (priorities 5 and 10), SPF, the three `hostingermail-{a,b,c}._domainkey` CNAMEs and an initial DMARC `p=none` policy. The owner created `notifications@sjcjournal.com` and confirmed `admin@sjcjournal.com` for the initial Super Admin login. The approved `admin@sjcjournal.com` alias delivers to the notifications inbox. The initial administrator must choose a website password and verify the email before accessing the dashboard; every subsequent role application requires Super Admin approval. The corrected scheduler runs successfully. At the owner’s request, 12 older contact notifications are preserved separately from new delivery.

The ignored local `.env.mail` is a private staging file; Laravel does not load it automatically. The completed settings have been applied to server `journal_app/.env`, outside `public_html`. Credentials and configuration backups must remain private and must never be committed or uploaded under the web root.

The initial Super Admin account has been created with an unknown random password and an unverified email address. Its setup email, **SJC Journal — Set up your Super Admin account**, arrived in the notifications inbox through the admin alias. The owner must use its private link to choose a website password, sign in as `admin@sjcjournal.com`, and verify the email before entering the dashboard. Setup links expire after 60 minutes; an expired password link can be replaced through `/forgot-password`, and email verification can be resent after signing in. No mailbox password is reused for the website account.

The temporary mail-configuration cron was removed after mail delivery and initial account creation were confirmed. Its two private setup helpers were moved to the File Manager trash. Only the regular scheduler and guarded mail worker remain scheduled.

Configured settings:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=notifications@sjcjournal.com
MAIL_PASSWORD="<private mailbox password>"
MAIL_TIMEOUT=15
MAIL_FROM_ADDRESS=notifications@sjcjournal.com
MAIL_FROM_NAME="Singapore Journal of Cardiology"
CONTACT_NOTIFICATION_EMAIL=info@larixjournals.com
ACCOUNT_NOTIFICATION_EMAIL=info@larixjournals.com
```

[Hostinger SMTP settings](https://www.hostinger.com/support/4305847-set-up-hostinger-email-on-your-applications-and-devices/). Configure the MX, SPF, DKIM and DMARC records shown in the Hostinger email dashboard at GoDaddy. Preserve website A/CNAME records and unrelated existing DNS entries.

Keep TLS certificate verification enabled. Check authentication before enabling SMTP in production; do not enable it with a blank password. Once the private configuration is complete, clear cached configuration and restart workers:

```sh
php artisan config:clear
php artisan queue:restart
php artisan mail:check --authenticate
```

`mail:check` displays only non-secret settings. `--authenticate` opens an SMTP connection and authenticates without sending mail. A successful result establishes connectivity and credentials, not inbox delivery. Send a real configuration test only with the owner's authorization, then verify receipt and spam placement.

The Hostinger worker runs every minute through a guarded entry point:

```sh
php /home/u709299470/domains/sjcjournal.com/journal_app/scripts/hostinger-mail-worker.php
```

The entry point preserves queued messages while SMTP credentials are missing, the mailer is `log`, or `storage/framework/mail-paused` exists. This private hold file can pause processing during delivery review; remove it only after the review is complete. When enabled, the worker processes `default,mail` with a 50-second limit and three attempts. Historical contact notifications are held in `mail-before-smtp-20261008`; the regular worker does not process that queue. Preserve them until the owner requests delivery. Keep that worker and `schedule:run` enabled. Both cron paths must use `domains/sjcjournal.com`; the old temporary domain folder no longer exists. Scheduled commands run in-process because this shared hosting installation disables `proc_open`.

Check `php artisan queue:failed` if delivery fails. After fixing the cause, retry only the affected failed job IDs; inspect recipients and avoid bulk replay of obsolete notices. Logs and backups of `.env` must remain private. After deploying new PHP classes, regenerate the production Composer class map; this installation uses an authoritative optimized map.
