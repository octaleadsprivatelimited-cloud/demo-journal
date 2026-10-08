# Journal email setup

Form enquiries and manuscript alerts go to `CONTACT_NOTIFICATION_EMAIL` as well as active staff recipients. Account application and decision alerts go to `ACCOUNT_NOTIFICATION_EMAIL` and active Super Admins. A staff account using the office inbox receives one copy. Contact submitters and account applicants receive receipts; applicants receive approval or rejection decisions. Existing manuscript, review, proof, password recovery and newsletter notifications use the same configured mailer. New notifications and verification messages run after commit on the `mail` queue.

Password registrations queue email verification immediately, before approval. The signed signup link confirms the current address without logging in, activating the account, or granting a role. It expires after 60 minutes by default and is invalid after the account email changes. Every role still requires Super Admin approval. Pending applicants can request another link at `/registration/verification`; responses do not disclose whether an address has an application, and requests are throttled. Google applications with a verified identity do not receive duplicate verification messages. Approval sends a new verification link only if the email is still unverified.

## Current Hostinger deployment

The requested sender is a new Hostinger mailbox, `notifications@sjcjournal.com`. The editorial recipient remains `info@larixjournals.com`. Domain DNS is managed in GoDaddy. Mailbox creation, email DNS records and SMTP credentials must be completed before enabling delivery. The last production audit found the mailer set to `log`, so **external email delivery has not yet been verified**. Do not use a log fallback to claim successful inbox delivery.

An ignored local `.env.mail` file is prepared for the mailbox owner to enter the new mailbox password privately. It is a staging file; Laravel does not load it automatically. Apply the completed settings to the private server `journal_app/.env`, outside `public_html`. Do not commit, upload publicly, or paste the password into chat.

Expected settings, subject to confirmation from the mailbox provider:

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

Hostinger already runs a worker every minute:

```sh
php artisan queue:work --queue=default,mail --stop-when-empty --max-time=50 --tries=3
```

Keep that worker and `schedule:run` enabled. Check `php artisan queue:failed` if delivery fails. After fixing the cause, retry only the affected failed job IDs; inspect recipients and avoid bulk replay of obsolete notices. Logs and backups of `.env` must remain private.
