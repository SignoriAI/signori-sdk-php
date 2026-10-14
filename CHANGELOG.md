# Changelog

All notable changes to the Signori PHP SDK.

## [Unreleased]

### Breaking
- The base URL is required. The `https://api.signori.ai` default is gone; pass `$baseUrl` to `Signori::client()` or set `SIGNORI_BASE_URL`, otherwise `ConfigurationException` is thrown.
- `Webhooks::create()` takes a required `$secret` argument; the API rejects webhooks without one.
- `Templates::prefillFromContact()` returns the field-value map itself instead of `['field_values' => [...]]`.

### Fixed
- `Documents::void()` calls `DELETE /api/v1/documents/{id}` with `reason` as a query parameter (the old `POST /documents/{id}/void` route does not exist).
- `Signers::get()` looks the signer up in `GET /api/v1/documents/{id}/signers` (there is no single-signer route).
- `Documents::signers()` follows pagination instead of returning only the first 20 signers.
- `Templates::createDocument()` sends empty `field_values` as a JSON object, not `[]`.

### Added
- `ConfigurationException` (extends `SignoriException`), also thrown for an empty API key.
- `Signers::list()`; optional `$scopes` on `ApiKeys::create()`; `$limit` / `$cursor` on `Webhooks::list()` and `ApiKeys::list()`.
- Route-table test that pins every SDK call to an API route.

## [1.0.0] — 2026-03-27

Initial release.

### Added
- `Signori::client()` — factory with env-var config and explicit parameter support
- `CurlHttpClient` — zero-dependency transport using PHP's built-in cURL extension
- `PsrHttpClient` — adapter for PSR-18 clients (Guzzle, Symfony HttpClient, etc.)
- `Documents` resource — upload, list, get, send, void, download, downloadSigned, auditTrail
- `Signers` resource — add, get, remind, remove
- `Templates` resource — list, get, createDocument, prefillFromContact
- `Webhooks` resource — create, list, update, delete, test, verify, constructEvent
- `ApiKeys` resource — create, list, delete
- Typed response DTOs — DocumentResponse, SignerResponse, TemplateResponse, WebhookResponse, AuditTrailResponse, ApiKeyResponse, PaginatedResponse
- Typed exception hierarchy — AuthException, NotFoundException, ValidationException, RateLimitException, ApiException
- Auto-retry on 429 (respects Retry-After) and 5xx errors (configurable)
- PHPUnit unit tests with MockHttpClient (no network required)
- Integration tests with auto-skip when SIGNORI_API_KEY is not set
- Examples: upload-and-send, from-template, webhook-handler, download-signed
