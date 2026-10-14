<?php

declare(strict_types=1);

namespace Signori\Resources;

use Signori\Exceptions\SignoriException;
use Signori\Responses\PaginatedResponse;
use Signori\Responses\WebhookResponse;
use Signori\Signori;

final class Webhooks
{
    public function __construct(private readonly Signori $client) {}

    /**
     * Register a new webhook endpoint.
     *
     * @param  string[] $events  e.g. ['document.completed', 'signer.signed']
     * @param  string   $secret  HMAC signing secret (8–255 chars) used for
     *                           the X-Signori-Signature header. Required by the API.
     */
    public function create(string $url, array $events, string $secret): WebhookResponse
    {
        $data = $this->client->post('/api/v1/webhooks', [
            'url'    => $url,
            'events' => $events,
            'secret' => $secret,
        ]);
        return WebhookResponse::from($data);
    }

    /**
     * List registered webhooks.
     */
    public function list(int $limit = 20, ?string $cursor = null): PaginatedResponse
    {
        $data = $this->client->get('/api/v1/webhooks', [
            'limit'  => $limit,
            'cursor' => $cursor,
        ]);
        return PaginatedResponse::from($data, fn(array $w) => WebhookResponse::from($w));
    }

    /**
     * Update a webhook.
     *
     * @param  array{url?: string, events?: string[], secret?: string, is_active?: bool} $changes
     */
    public function update(string $id, array $changes): WebhookResponse
    {
        $data = $this->client->patch("/api/v1/webhooks/{$id}", $changes);
        return WebhookResponse::from($data);
    }

    /**
     * Delete a webhook by ID.
     */
    public function delete(string $id): void
    {
        $this->client->delete("/api/v1/webhooks/{$id}");
    }

    /**
     * Send a test event to a webhook endpoint.
     */
    public function test(string $id): array
    {
        return $this->client->post("/api/v1/webhooks/{$id}/test");
    }

    // -------------------------------------------------------------------------
    // Webhook signature verification
    // -------------------------------------------------------------------------

    /**
     * Verify an incoming webhook payload against its HMAC-SHA256 signature.
     *
     * Usage in your webhook handler:
     *
     *   $payload   = file_get_contents('php://input');
     *   $signature = $_SERVER['HTTP_X_SIGNORI_SIGNATURE'] ?? '';
     *   $secret    = 'your-webhook-secret';
     *
     *   if (! Webhooks::verify($payload, $signature, $secret)) {
     *       http_response_code(403);
     *       exit;
     *   }
     *
     * @param  string $payload   Raw request body (do not decode).
     * @param  string $signature The X-Signori-Signature header value.
     * @param  string $secret    Your webhook signing secret.
     */
    public static function verify(string $payload, string $signature, string $secret): bool
    {
        if ($payload === '' || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        // Strip optional "sha256=" prefix using str_starts_with, not ltrim
        // (ltrim strips characters, not substrings — it would eat hex chars)
        $raw = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        // Constant-time comparison prevents timing attacks
        return hash_equals($expected, $raw);
    }

    /**
     * Decode the webhook payload and return the event type and data.
     *
     * @throws SignoriException if the payload is not valid JSON
     * @return array{event: string, data: array<string, mixed>}
     */
    public static function constructEvent(string $payload): array
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new SignoriException('Webhook payload is not valid JSON');
        }
        return [
            'event' => (string) ($decoded['event'] ?? ''),
            'data'  => is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded,
        ];
    }
}
