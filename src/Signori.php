<?php

declare(strict_types=1);

namespace Signori;

use Signori\Exceptions\SignoriException;
use Signori\HttpClient\CurlHttpClient;
use Signori\HttpClient\HttpClientInterface;
use Signori\Resources\ApiKeys;
use Signori\Resources\Documents;
use Signori\Resources\IframeOrigins;
use Signori\Resources\Signers;
use Signori\Resources\Templates;
use Signori\Resources\Webhooks;

/**
 * Signori PHP SDK — main client.
 *
 * Quick start:
 *
 *   $sv = Signori::client('your-api-key');
 *   $doc = $sv->documents->upload('/path/to/contract.pdf', 'Master Services Agreement');
 *   $sv->documents->send($doc->id, [
 *       ['email' => 'alice@example.com', 'full_name' => 'Alice Smith'],
 *   ]);
 */
final class Signori
{
    private string $apiKey;
    private string $baseUrl;
    private HttpClientInterface $http;
    private int $timeout;
    private int $maxRetries;

    private Documents $documents;
    private Signers $signers;
    private Templates $templates;
    private Webhooks $webhooks;
    private ApiKeys $apiKeys;
    private IframeOrigins $iframeOrigins;

    private function __construct(
        string $apiKey,
        string $baseUrl,
        HttpClientInterface $http,
        int $timeout,
        int $maxRetries,
    ) {
        if ($apiKey === '') {
            throw new SignoriException('API key must not be empty. Pass it explicitly or set SIGNORI_API_KEY.');
        }

        $this->apiKey    = $apiKey;
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->http      = $http;
        $this->timeout   = $timeout;
        $this->maxRetries = $maxRetries;

        $this->documents = new Documents($this);
        $this->signers   = new Signers($this);
        $this->templates = new Templates($this);
        $this->webhooks      = new Webhooks($this);
        $this->apiKeys       = new ApiKeys($this);
        $this->iframeOrigins = new IframeOrigins($this);
    }

    /**
     * Create a client from explicit values or environment variables.
     *
     * @param  string|null $apiKey   Bearer token. Falls back to SIGNORI_API_KEY env var.
     * @param  string|null $baseUrl  API base URL. Falls back to SIGNORI_BASE_URL env var,
     *                               then https://api.signori.ai.
     * @param  int         $timeout  Request timeout in seconds. Default 30.
     * @param  int         $maxRetries Auto-retry count for 429 / network errors. Default 1.
     */
    public static function client(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        int $maxRetries = 1,
    ): self {
        $resolvedKey = $apiKey ?? (string) getenv('SIGNORI_API_KEY');
        $resolvedUrl = $baseUrl
            ?? ((string) getenv('SIGNORI_BASE_URL') ?: 'https://api.signori.ai');

        return new self(
            $resolvedKey,
            $resolvedUrl,
            new CurlHttpClient($timeout),
            $timeout,
            $maxRetries,
        );
    }

    /**
     * Replace the HTTP transport with a PSR-18 compatible client.
     *
     * Useful for testing (swap in a mock) or if you already have
     * Guzzle / Symfony HttpClient in your project:
     *
     *   $sv = Signori::client()->withHttpClient(
     *       new \Signori\HttpClient\PsrHttpClient($guzzle, $psr17Factory)
     *   );
     */
    public function withHttpClient(HttpClientInterface $client): self
    {
        return new self(
            $this->apiKey,
            $this->baseUrl,
            $client,
            $this->timeout,
            $this->maxRetries,
        );
    }

    /**
     * Expose resource properties as public readonly via magic getter.
     * Prevents external mutation while allowing clone-free construction.
     */
    public function __get(string $name): mixed
    {
        return match($name) {
            'documents' => $this->documents,
            'signers'   => $this->signers,
            'templates' => $this->templates,
            'webhooks'      => $this->webhooks,
            'apiKeys'       => $this->apiKeys,
            'iframeOrigins' => $this->iframeOrigins,
            default         => throw new \InvalidArgumentException("Unknown property: {$name}"),
        };
    }

    // -------------------------------------------------------------------------
    // Internal helpers used by Resource classes
    // -------------------------------------------------------------------------

    /** @internal */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, query: $query);
    }

    /** @internal */
    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, json: $body);
    }

    /** @internal */
    public function patch(string $path, array $body = []): array
    {
        return $this->request('PATCH', $path, json: $body);
    }

    /** @internal */
    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, json: $body);
    }

    /** @internal */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Upload a file via multipart/form-data.
     *
     * @internal
     * @param  string|resource $file   File path, open resource handle, or raw bytes.
     */
    public function upload(string $path, mixed $file, string $filename, array $extra = []): array
    {
        return $this->request('POST', $path, multipart: [
            'file'     => $file,
            'filename' => $filename,
            'extra'    => $extra,
        ]);
    }

    /** @internal */
    public function getBytes(string $path): string
    {
        return $this->requestRaw('GET', $path);
    }

    // -------------------------------------------------------------------------
    // Core request dispatcher
    // -------------------------------------------------------------------------

    private function request(
        string $method,
        string $path,
        array  $query     = [],
        array  $json      = [],
        array  $multipart = [],
    ): array {
        $attempt = 0;
        do {
            $attempt++;
            [$status, $body] = $this->http->send(
                method:     $method,
                url:        $this->buildUrl($path, $query),
                headers:    $this->headers(),
                json:       $json,
                multipart:  $multipart,
            );
            if ($status === 429 && $attempt <= $this->maxRetries) {
                $retryAfter = $body['retry_after'] ?? 1;
                sleep((int) $retryAfter);
                continue;
            }
            if ($status >= 500 && $attempt <= $this->maxRetries) {
                sleep(1);
                continue;
            }
            break;
        } while (true);

        return $this->handleResponse($status, $body);
    }

    private function requestRaw(string $method, string $path): string
    {
        return $this->http->sendRaw(
            method:  $method,
            url:     $this->buildUrl($path),
            headers: $this->headers(),
        );
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept'        => 'application/json',
            'User-Agent'    => 'signori-php/1.0.0',
        ];
    }

    private function buildUrl(string $path, array $query = []): string
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query(array_filter($query, fn($v) => $v !== null));
        }
        return $url;
    }

    private function handleResponse(int $status, array $body): array
    {
        if ($status >= 200 && $status < 300) {
            // Unwrap Signori envelope: {"data": {...}, "request_id": "..."}
            return $body['data'] ?? $body;
        }

        $error   = $body['error'] ?? [];
        $code    = $error['code'] ?? 'UNKNOWN';
        $message = $error['message'] ?? "HTTP {$status}";
        $reqId   = $body['request_id'] ?? null;

        throw Exceptions\SignoriException::fromApiError($status, $code, $message, $reqId);
    }
}
