# Changelog

All notable changes to the SignVault PHP SDK.

## [1.0.0] — 2026-03-27

Initial release.

### Added
- `SignVault::client()` — factory with env-var config and explicit parameter support
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
- Integration tests with auto-skip when SIGNVAULT_API_KEY is not set
- Examples: upload-and-send, from-template, webhook-handler, download-signed
