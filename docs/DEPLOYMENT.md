# Production deployment

## Release checklist

1. Provision PostgreSQL, Redis, durable object storage, TLS, DNS, and an email provider.
2. Put all secrets in the platform secret manager; do not upload `.env` to the repository or bake it into an image.
3. Build an immutable production image with `docker build --target production -t journal:<release> .`.
4. Run the database backup procedure before schema changes.
5. Run `php artisan migrate --force` once per release, from a dedicated release job.
6. Start or roll PHP-FPM, Nginx, queue worker, and scheduler processes.
7. Run `php artisan optimize`, restart workers with `php artisan queue:restart`, and verify `/up`.
8. Smoke-test login, article submission, review assignment, publication, contact email, uploaded media, sitemap, and a 404.

Set `APP_ENV=production`, `APP_DEBUG=false`, a long random `APP_KEY`, `APP_FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, and a restrictive production log level. Use a non-superuser PostgreSQL role limited to the application database. Configure `APP_URL` with the canonical HTTPS origin so signed URLs, canonical metadata, notifications, and Sanctum use the right host.

Production releases normally run `migrate --force` without seeding. When baseline records are needed, `db:seed --force` installs roles, permissions, and settings and only creates an administrator when both `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` are supplied securely at runtime. Keep `SEED_DEMO_CONTENT=false`; the application rejects demo content seeding outside `local` and `testing` environments.

## Docker / VPS

Use the `production` target for the PHP application and the `web` target for Nginx. Do not publish the PHP-FPM, PostgreSQL, or Redis ports to the internet. Terminate TLS at a managed load balancer or a hardened edge proxy and forward only to Nginx. Run at least two stateless app instances behind the load balancer, one or more queue workers, and exactly one scheduler leader.

Persist only PostgreSQL, Redis when durability matters, and `storage/app`. Prefer `MEDIA_DISK=s3`; a local filesystem volume complicates horizontal scaling. Set health probes to `/up`. Configure container memory and CPU limits, log rotation, and automatic restart without allowing endless crash loops.

## Laravel Forge, DigitalOcean, Hostinger, or a traditional VPS

- Point the site root to `public`, never the repository root.
- Use PHP 8.3 or newer with the extensions listed in the README.
- Run Nginx under an unprivileged service account and grant write access only to `storage` and `bootstrap/cache`.
- Install a Supervisor process for `php artisan queue:work --queue=default,mail --sleep=2 --tries=3 --max-time=3600` and restart it after every release.
- Add one cron entry: `* * * * * cd /path/to/current && php artisan schedule:run >> /dev/null 2>&1`.
- Deploy into versioned release directories and atomically switch a `current` symlink after migrations and smoke checks.

Forge can manage workers, scheduler, TLS, and deploy hooks. DigitalOcean Managed PostgreSQL/Redis or AWS RDS/ElastiCache reduces backup and failover work. Hostinger VPS requires you to configure those controls yourself.

## AWS reference deployment

Run the production image on ECS/Fargate or EC2 Auto Scaling, use an Application Load Balancer, RDS PostgreSQL with Multi-AZ and point-in-time recovery, ElastiCache Redis, S3 with versioning and lifecycle rules, SES for email, CloudFront for assets, and Secrets Manager for runtime credentials. Grant the task role access only to the required S3 prefix and secret ARNs. Do not ship long-lived AWS keys when an IAM role is available.

## Zero-downtime details

- Prefer additive migrations: add nullable columns or new tables, deploy compatible code, backfill asynchronously, then tighten constraints in a later release.
- Set an appropriate maintenance strategy for destructive migrations and test restore time before the change window.
- Keep old queue workers compatible with jobs already enqueued during a rolling deploy.
- Publish assets with content hashes before switching application traffic.
- If using a CDN, purge only HTML or mutable paths; hashed Vite assets can remain cached for a year.

## Observability

Send JSON application logs to a centralized sink and alert on 5xx rate, queue failures, database saturation, storage errors, mail rejection, and elevated login throttling. Run `php artisan queue:failed` as part of incident diagnosis. Track request latency and scheduled-job heartbeat separately from the `/up` liveness endpoint.
