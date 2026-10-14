<?php

declare(strict_types=1);

namespace Signori\Resources;

use Signori\Responses\ApiKeyResponse;
use Signori\Responses\PaginatedResponse;
use Signori\Signori;

final class ApiKeys
{
    public function __construct(private readonly Signori $client) {}

    /**
     * Create a new API key. The secret value is only returned once — store it.
     *
     * @param  string[] $scopes  Omit to let the API apply its default (["read"]).
     */
    public function create(string $name, array $scopes = []): ApiKeyResponse
    {
        $body = ['name' => $name];
        if ($scopes !== []) {
            $body['scopes'] = array_values($scopes);
        }
        $data = $this->client->post('/api/v1/api-keys', $body);
        return ApiKeyResponse::from($data);
    }

    /**
     * List all API keys for the company (secrets are masked).
     */
    public function list(int $limit = 20, ?string $cursor = null): PaginatedResponse
    {
        $data = $this->client->get('/api/v1/api-keys', [
            'limit'  => $limit,
            'cursor' => $cursor,
        ]);
        return PaginatedResponse::from($data, fn(array $k) => ApiKeyResponse::from($k));
    }

    /**
     * Revoke an API key permanently.
     */
    public function delete(string $id): void
    {
        $this->client->delete("/api/v1/api-keys/{$id}");
    }
}
