<?php

declare(strict_types=1);

namespace SignVault\Tests;

use SignVault\HttpClient\HttpClientInterface;

/**
 * In-memory HTTP stub.
 *
 * Usage:
 *   $http = new MockHttpClient();
 *   $http->enqueue(200, ['id' => 'doc_1', 'title' => 'Test']);
 *   $sv = SignVault::client('test-key')->withHttpClient($http);
 *   // next request returns the queued response; asserts the correct URL/method
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var array<int, array{status: int, body: array}> */
    private array $queue = [];

    /** @var array<int, array{method: string, url: string, headers: array, json: array, multipart: array}> */
    private array $recorded = [];

    public function enqueue(int $status, array $body): self
    {
        $this->queue[] = ['status' => $status, 'body' => $body];
        return $this;
    }

    public function send(
        string $method,
        string $url,
        array  $headers   = [],
        array  $json      = [],
        array  $multipart = [],
    ): array {
        $this->recorded[] = compact('method', 'url', 'headers', 'json', 'multipart');
        $response = array_shift($this->queue)
            ?? throw new \UnderflowException("MockHttpClient queue is empty. Enqueue a response first.");
        return [$response['status'], $response['body']];
    }

    public function sendRaw(string $method, string $url, array $headers = []): string
    {
        $this->recorded[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'json' => [], 'multipart' => []];
        $response = array_shift($this->queue)
            ?? throw new \UnderflowException("MockHttpClient queue is empty.");
        return $response['body']['_raw'] ?? '';
    }

    /** Return the nth recorded request (0-indexed). */
    public function requestAt(int $index): array
    {
        return $this->recorded[$index]
            ?? throw new \OutOfBoundsException("No recorded request at index {$index}");
    }

    public function lastRequest(): array
    {
        return $this->requestAt(count($this->recorded) - 1);
    }

    public function requestCount(): int
    {
        return count($this->recorded);
    }
}
