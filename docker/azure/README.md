# Standalone Azure journal deployment

This deployment uses a separate B2ats v2 Ubuntu VM, Standard HDD OS disk, and Standard IPv4 in Central India. It does not share the FRANCH resources.

- Website: https://journal-sjc-bb469c5c.centralindia.cloudapp.azure.com
- Resource group: journal-prod-rg
- Server: journal-vm
- Files and compose configuration: /opt/journal
- Public ports: 80 and 443; SSH is restricted to the deployment computer's public IP.
- Database and uploads persist on the VM disk. Caddy manages HTTPS certificates.
- Supervisor runs nginx, PHP-FPM and the queue worker. Host cron runs Laravel's scheduler every minute.
- All localhost bypass flags are disabled, production mode is enabled, and cookies require HTTPS.
- Production credentials are generated on the destination; do not commit or upload local .env files.
- Administrator email: info@octaleads.com. The administrator password was changed to the user-specified value; no plaintext password is stored on the server.

Use the administrator password you selected. The original generated password has been removed.

Daily backups are scheduled at 03:00 UTC and retained on the server for seven days.

## Operations

Connect with the deployment key in storage/app/backups/azure-deploy/journal-ssh and the pinned known_hosts file in the same directory. These files and the database/files backup are excluded from Git and Docker context.

On the server, run `cd /opt/journal && sudo docker compose ps` for status. Use `sudo docker compose logs --tail=100 app` for application logs. Never print .env to logs.

The fresh credentials and disabled login bypass mean existing local sessions cannot access production. Localhost remains unchanged.

Email transport currently uses the log driver; SMTP/provider configuration is required for real invitations, verification and password-reset delivery. External DOI registration and indexing still require their respective services.

The small VM is for low traffic. Monitor memory, disk and response time before increasing traffic. Same-server files are not an offsite backup; the migration snapshot on the deployment computer should be supplemented with scheduled offsite backups before relying on the site for ongoing production submissions.

Cost estimate: approximately US$10–12/month before tax, bandwidth and other usage, without assuming free-tier credits. The verified VM compute rate was US$0.00615/hour in Central India on 10 September 2026. Azure billing is usage-based, not a hard spending cap.

## Deployment verification

Verified HTTPS public homepage, articles, archive, categories, authors and health endpoint. Administrator sign-in succeeded and authenticated dashboard, articles, submissions, reviews, uploads, users and settings returned HTTP 200. Unauthenticated admin/author/reviewer dashboards redirected to their login pages. Browser homepage rendering passed with no console errors.

Migrated counts: 57 article records (including draft/trash), 13 authors, 20 reviews, 46 submission rounds, 3 workflow files. Six articles are published. No referenced article PDFs, featured images or workflow files were missing. Runtime is production with debug and all four localhost bypass flags disabled.

## Microsoft email setup (awaiting access approval)

Created `journal-email` and `journal-mail-bb469c5c` in `journal-prod-rg`. The managed sender domain is `cd162083-993c-4453-a8ae-e1a3abd308e1.azurecomm.net`; no octaleads.com DNS records were changed.

The proposed password-free authentication is the journal VM's system-assigned managed identity with the custom role in `email-role.json`, assigned only at the `journal-mail-bb469c5c` communication resource scope. Automatic approval review rejected enabling that VM identity pending explicit approval of the access change. No VM identity or role assignment was made. Production remains on the log mail driver until authentication, application transport configuration and an authorized test send are completed.

### Account settings and notification branding (2026-09-10)

Production app image: `journal-azure:account-20260910`.
Administrators can change their own sign-in email and password at `/admin/account`, with current-password verification. Account changes invalidate other database sessions and queue an account-change notice. Shared HTML and plain-text notification layouts use the journal logo, name, website and public contacts from Site settings. `/admin/email-templates` offers design previews without sending messages.

Validation: 11 focused account, workflow-notification and publication tests passed (72 assertions). Production sign-in, account form, template index and rendered logo/contact footer were checked in the browser. Outbound delivery remains on the log mailer pending approval for Azure managed-identity access; template deployment does not enable email delivery.

### Distinct notification designs

The `journal-azure:templates-20260910` image replaces generic previews with twelve sample messages and purpose-specific headings, accents, actions, security callouts, progress steps and proof checklists. Actual notifications use the same layouts while retaining their original message and secure action URL. Twelve focused tests passed (111 assertions), including every design and the real password-reset token link. Reset and proof previews were visually inspected on localhost. Mail delivery configuration is unchanged.

### Email password recovery

Recovery is available from each login form via `/forgot-password`. The `journal-azure:recovery-20260910` image includes clear recovery instructions, expired-link retry navigation, database-session revocation on reset, and an Azure Communication Services mail transport using VM managed identity. Seven focused tests passed (81 assertions): valid reset, token reuse rejection, expiry rejection, branded links, account controls, and mocked Azure API acceptance/authentication failure.

Activation still requires explicit approval of the previously rejected access change: enable the journal VM system-assigned identity and assign the prepared custom role only to `journal-mail-bb469c5c`. Then configure `MAIL_MAILER=azure`, `AZURE_EMAIL_ENDPOINT=https://journal-mail-bb469c5c.unitedstates.communication.azure.com` and the managed-domain sender address returned by Azure. Recreate the app and test one authorized recovery email; verify the Azure send operation status without printing reset tokens. Do not replay historical queued notifications without checking their recipients and relevance. API HTTP 202 means accepted for processing, not confirmed inbox delivery. Until activation, production recovery reports that email recovery is unavailable instead of claiming a reset email was sent.

### Client page checklist (2026-09-11)

Image `journal-azure:client-pages-20260911` adds `/policies`, 44 editable guidance/policy pages, `/corrections-retractions`, `/current-issue`, `/admin/pages` and a separate grouped footer section. All 50 workbook entries are mapped in `docs/CLIENT_PAGE_CHECKLIST.md`. Draft policy prompts stay private until an administrator supplies and confirms publication wording. Existing published policy settings remain the initial content; use the new page manager for subsequent revisions. Journal identifiers remain in Site settings, and indexing shows verified services only. Fifteen focused tests passed (232 assertions). No new Azure services or permissions are required.

### SJC content and simplified administration release (2026-09-11)

Current production image: `journal-azure:sjc-20260911`. Source snapshot: `/opt/journal/releases/sjc-20260911`.

Deployed the simplified admin menu/dashboard, author-profile management, compact contributor summaries, standards/resources above the footer, clickable article cards, and the PDF.js reader. The Your Perspective form now uses aligned full-width fields, consistent labels/spacing, and a full-width submit button on desktop and mobile.

Merged 39 SJC articles, 176 author profiles and 7 editorial members without replacing production credentials, settings or real submissions. All 39 published articles have local private PDFs. Moved the six identified demo articles to trash and removed their seeded review/submission/view data (including already-trashed seed articles). Production accounts were preserved.

Rollback snapshots: `/opt/journal/backups/sjc-20260911/{before-release.dump,uploads.tar.gz,compose.yml}`. Existing daily backups continue unchanged. The preceding image is `journal-azure:client-pages-20260911`.

Verification: public HTTPS homepage, articles, authors, editorial board and policies returned 200; unauthenticated admin access redirected to login. Live PDF page navigation passed. The form fields measured equal widths at 390px, there was no horizontal page overflow, and browser JavaScript errors were empty. Runtime remains production, debug false, all four localhost bypass flags false; no imported PDF files were missing. Eight focused local tests passed (64 assertions), including comment moderation and PDF access.
### UI release (2026-09-13)

Production image: `journal-azure:ui-20260913-final`. Includes desktop hero editorial sidebar (hidden below 900px), archive navigation/year grouping, year search, Resources page and three PDFs at `/author-resources/`, article-title sizing and dashboard workflow guide. The Resources page and PDF directory use different paths to avoid nginx directory redirects.

Rollback image: `journal-azure:pages-20260912`. Database backup and original Compose configuration are in `/opt/journal/releases/ui-20260913/`. No production database content or upload volume was replaced.

Verified HTTPS 200 for home, archive, Resources, year search, authors, admin login and health; all three downloads returned valid PDF headers. Homepage HTML references the new hero/sidebar and CSS bundle. Deployment used Azure CLI Run Command; keep encoded scripts under 64 KB because larger payloads did not execute on this VM.
