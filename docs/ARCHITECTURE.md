# Architecture

## Runtime

Nginx serves immutable assets and forwards application requests to PHP-FPM. Laravel uses PostgreSQL as the system of record, Redis for cache and rate-limit state, a database-backed queue by default, and Laravel Storage for local or S3-compatible media. Queue workers send notifications and process background work; the scheduler publishes due articles and performs maintenance.

The Docker Compose development topology mirrors production responsibilities:

```text
Browser -> Nginx -> Laravel/PHP-FPM -> PostgreSQL
                      |       |
                      |       +-> Redis
                      +-> Storage/S3

Queue worker -----> PostgreSQL/Redis/Mail provider
Scheduler --------> editorial jobs and queue
```

## Application boundaries

- `app/Models` contains persistence relationships and narrow query scopes.
- `app/Http/Requests` owns validation and request-level authorization.
- `app/Policies` is the final server-side authorization boundary.
- `app/Services` owns search, media, analytics, and editorial state transitions.
- `app/Actions` models focused use cases that span multiple records.
- `app/Events`, `Listeners`, `Jobs`, and `Notifications` isolate asynchronous side effects.
- Controllers coordinate validated requests and services; they do not own workflow rules.
- Public, authentication, and portal routes are separated to keep middleware boundaries visible.

## Editorial state machine

```text
Draft -> Submitted -> Under review -> Revision required
                     |                     |
                     |                     +-> Submitted (new version)
                     +-> Approved -> Scheduled -> Published
                     +-> Rejected
```

Transitions are performed by the editorial workflow service inside a database transaction. A transition records the actor, prior and new states, context, timestamps, and an article version where appropriate. Controllers cannot bypass policy checks or write arbitrary statuses.

Reviewers receive an assignment record and may submit comments and a recommendation. They cannot modify manuscript content. Authors can change their own drafts and revision-required manuscripts, but a submitted version is preserved before later edits.

## Authorization

Roles group granular permissions. Route middleware rejects users without the broad portal role, while policies check the specific record and action. Super Admin has a server-side gate override. Account suspension is checked after authentication. UI visibility is only a convenience and is never the authorization boundary.

## Search and scale

The search service is the stable interface used by controllers. Its default implementation applies normalized PostgreSQL-aware filters and indexed columns. This boundary can be replaced with Laravel Scout plus Meilisearch or OpenSearch without changing public or portal controllers.

List queries paginate and eager-load display relationships. Slugs and public UUIDs are indexed. Article views use privacy-conscious visitor hashes rather than raw long-lived identifiers. High-volume analytics can later be exported to a warehouse without changing the core article model.

## Media and rich content

The media service validates MIME type, extension, size, visibility, and ownership before storing through Laravel Storage. Metadata, captions, and alt text remain in PostgreSQL; bytes may live on local durable storage or S3. Rendered Blade content escapes ordinary values. Any administrator-authored rich HTML must pass through the configured sanitizer before persistence or rendering.

## Failure behavior

Transactional editorial operations roll back together. Queued notifications are retryable and do not determine whether the primary database action succeeds. Public pages expose branded HTTP error responses, while production logging captures the exception without leaking a stack trace to users.
