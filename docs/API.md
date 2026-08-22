# REST API

The API is versioned under `/api/v1` and returns JSON. Public publication endpoints are read-only. Authenticated author endpoints require a Sanctum token with the appropriate ability and are still checked by model policies.

## Authentication

Create a personal token through `POST /api/v1/tokens` with email, password, device name, and requested abilities. Send the returned token once as:

```http
Authorization: Bearer <token>
Accept: application/json
```

Use `DELETE /api/v1/tokens/current` to revoke the token used for the request. Browser-based same-origin clients may use Sanctum's stateful cookie mode instead.

## Public resources

- `GET /api/v1/articles` supports `q`, `category`, `tag`, `author`, `from`, `to`, `sort`, and cursor/page parameters.
- `GET /api/v1/articles/{slug}` returns a published article and its display relationships.
- `GET /api/v1/categories` returns active categories with publication counts.
- `GET /api/v1/authors/{slug}` returns a public profile and published work.

## Authenticated resources

- `GET /api/v1/user` returns the current user and roles.
- `GET /api/v1/author/articles` lists the current author's manuscripts.
- `POST /api/v1/author/articles` creates a draft.
- `PATCH /api/v1/author/articles/{article}` updates an authorized draft or revision.
- `POST /api/v1/author/articles/{article}/submit` performs a validated editorial transition.

Validation errors use HTTP 422 with Laravel's standard `message` and `errors` object. Authentication and authorization failures use 401 and 403. Rate limits return 429 with retry headers. API clients must never infer authorization from a field shown in a previous response.

The resource serializers are the compatibility boundary. Add fields without removing or changing existing semantics inside a version; publish `/api/v2` for breaking changes.
