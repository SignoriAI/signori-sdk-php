<?php

declare(strict_types=1);

namespace Signori\HttpClient;

/**
 * Minimal HTTP transport interface used internally by the SDK.
 *
 * Two methods:
 *  - send()    → [statusCode, decodedJsonBody]
 *  - sendRaw() → raw response bytes (for PDF downloads)
 *
 * Implement this to swap in a PSR-18 client or a test double.
 */
interface HttpClientInterface
{
    /**
     * Execute an HTTP request and return [status, decoded body].
     *
     * @param  array<string,string> $headers
     * @param  array<string,mixed>  $json       JSON body (POST/PATCH)
     * @param  array<string,mixed>  $multipart  Multipart file upload fields
     * @return array{0: int, 1: array<string,mixed>}
     */
    public function send(
        string $method,
        string $url,
        array  $headers    = [],
        array  $json       = [],
        array  $multipart  = [],
    ): array;

    /**
     * Execute an HTTP GET and return the raw response body as a string.
     *
     * @param  array<string,string> $headers
     */
    public function sendRaw(string $method, string $url, array $headers = []): string;
}
