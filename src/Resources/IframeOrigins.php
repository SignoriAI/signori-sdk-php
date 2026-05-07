<?php

declare(strict_types=1);

namespace Signori\Resources;

use Signori\Responses\IframeOriginResponse;
use Signori\Signori;

/**
 * Per-company allowlist of origins permitted to embed the signing page in
 * an iframe. The signing UI builds its
 * ``Content-Security-Policy: frame-ancestors`` header from these entries
 * on every ``/sign/<token>`` page render.
 *
 * Origins must be of the form ``https://host[:port]``,
 * ``https://*.host`` (CSP wildcard), or ``http://localhost[:port]``
 * for development. Empty list → embedding is disallowed.
 */
final class IframeOrigins
{
    public function __construct(private readonly Signori $client) {}

    /**
     * Add an origin to the company's iframe allowlist. Re-adding a
     * previously-removed origin reactivates the existing row in place
     * rather than creating a duplicate.
     */
    public function create(string $origin): IframeOriginResponse
    {
        $data = $this->client->post('/api/v1/iframe-origins', ['origin' => $origin]);
        return IframeOriginResponse::from($data);
    }

    /**
     * List all entries (active + revoked) for the company.
     *
     * @return list<IframeOriginResponse>
     */
    public function list(): array
    {
        $data = $this->client->get('/api/v1/iframe-origins');
        $items = is_array($data) ? $data : [];
        return array_map(
            static fn (array $item): IframeOriginResponse => IframeOriginResponse::from($item),
            $items,
        );
    }

    /**
     * Soft-delete (deactivate) an entry by id.
     */
    public function delete(string $id): void
    {
        $this->client->delete("/api/v1/iframe-origins/{$id}");
    }
}
