# Backup and restore

## Policy

Back up PostgreSQL and media independently, encrypt every backup, retain copies in a separate account or failure domain, and test restores on a schedule. A backup that has not been restored successfully is not considered valid.

Recommended starting objectives:

- PostgreSQL point-in-time recovery with a recovery point objective of 15 minutes or less.
- Daily logical backups retained for 35 days, monthly backups for 12 months.
- S3 versioning for media with cross-region replication when required by business continuity policy.
- Quarterly full restore exercises and a documented recovery time.

Adjust retention for privacy, contractual, and jurisdictional requirements.

## PostgreSQL logical backup

Run from a trusted administrative host with credentials supplied by its secret manager:

```bash
pg_dump --format=custom --no-owner --no-acl --file=journal-YYYYMMDD.dump "$DATABASE_URL"
pg_restore --list journal-YYYYMMDD.dump > journal-YYYYMMDD.manifest
```

Encrypt and upload the dump to protected object storage. Record the application release, migration version, PostgreSQL version, checksum, and encryption key identifier alongside it. Do not place database URLs or decrypted dumps in the repository.

Managed PostgreSQL should also have automated snapshots and point-in-time recovery. Logical dumps remain useful for portability and table-level inspection.

## Restore

Restore into a new empty database rather than overwriting the only working copy:

```bash
createdb journal_restore
pg_restore --exit-on-error --clean --if-exists --no-owner --no-acl --dbname=journal_restore journal-YYYYMMDD.dump
```

Point a non-production application release at the restored database, run `php artisan migrate:status`, and validate user counts, published article counts, relationships, search, downloads, and audit records. Promote the restored database only after application and operator checks pass.

## Media

For S3-compatible storage, enable bucket versioning, server-side encryption, least-privilege bucket policies, lifecycle transitions, and deletion protection for the backup role. Back up the database and media within the same documented window so references remain reconcilable.

For a local `storage/app` volume, snapshot the filesystem or use a backup agent that preserves paths and timestamps. Pause media mutations or use a consistent snapshot mechanism; copying a live mutable directory without consistency guarantees can miss or mismatch files.

## Redis and queues

Redis is treated as reconstructable cache state. Database queue jobs are protected by the PostgreSQL backup. Before a planned restore, stop public writes, queue workers, and the scheduler to avoid processing against the wrong database. After validation, restart workers and inspect failed or duplicated external side effects.
