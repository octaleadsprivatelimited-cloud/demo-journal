# Journal email setup

Form enquiries and manuscript alerts go to `CONTACT_NOTIFICATION_EMAIL` as well as active staff recipients. Account application and decision alerts go to `ACCOUNT_NOTIFICATION_EMAIL` and active Super Admins. A staff account using the office inbox receives one copy. Contact submitters and account applicants receive receipts; applicants receive approval or rejection decisions. Existing manuscript, review, proof, password recovery and newsletter notifications use the same configured mailer. New notifications and verification messages run after commit on the `mail` queue.

## Current Hostinger deployment

The selected sender and editorial inbox are `info@larixjournals.com`. Its DNS currently points to GoDaddy. SMTP credentials are still pending; the production mailer remains `log`, so **no external email delivery is enabled**. Do not use a log fallback to claim successful inbox delivery.

An ignored local `.env.mail` file is prepared for the mailbox owner to enter the existing password privately. It is a staging file; Laravel does not load it automatically. Apply the completed settings to the private server `journal_app/.env`, outside `public_html`. Do not commit, upload publicly, or paste the password into chat.

Expected settings, subject to confirmation from the mailbox provider:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtpout.secureserver.net
MAIL_PORT=465
MAIL_USERNAME=info@larixjournals.com
MAIL_PASSWORD="<existing mailbox password>"
MAIL_TIMEOUT=15
MAIL_FROM_ADDRESS=info@larixjournals.com
MAIL_FROM_NAME="Singapore Journal of Cardiology"
CONTACT_NOTIFICATION_EMAIL=info@larixjournals.com
ACCOUNT_NOTIFICATION_EMAIL=info@larixjournals.com
```

[GoDaddy SMTP settings](https://www.godaddy.com/help/set-up-third-party-plugins-or-websites-using-smtp-settings-42788).

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
