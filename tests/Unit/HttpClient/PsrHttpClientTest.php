<?php

declare(strict_types=1);

namespace SignVault\Tests\Unit\HttpClient;

use PHPUnit\Framework\TestCase;
use SignVault\HttpClient\PsrHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Tests that PsrHttpClient correctly wires PSR-7 request/response objects.
 * Uses nyholm/psr7 (dev dependency) for concrete PSR-7 implementations.
 */
final class PsrHttpClientTest extends TestCase
{
    private function makeMockPsrClient(int $status, string $body): ClientInterface
    {
        return new class($status, $body) implements ClientInterface {
            public ?RequestInterface $lastRequest = null;
            public function __construct(
                private readonly int    $status,
                private readonly string $body,
            ) {}
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->lastRequest = $request;
                $factory = new Psr17Factory();
                return new Response(
                    $this->status,
                    ['Content-Type' => 'application/json'],
                    $this->body,
                );
            }
        };
    }

    public function test_sends_authorization_header(): void
    {
        $factory    = new Psr17Factory();
        $mockClient = $this->makeMockPsrClient(200, '{"data":{"id":"x"}}');
        $transport  = new PsrHttpClient($mockClient, $factory, $factory);

        $transport->send('GET', 'https://api.test/path', ['Authorization' => 'Bearer key-abc']);

        $this->assertSame('Bearer key-abc', $mockClient->lastRequest->getHeaderLine('Authorization'));
    }

    public function test_sends_json_body_with_content_type(): void
    {
        $factory    = new Psr17Factory();
        $mockClient = $this->makeMockPsrClient(200, '{"data":{}}');
        $transport  = new PsrHttpClient($mockClient, $factory, $factory);

        $transport->send('POST', 'https://api.test/path', [], ['key' => 'value']);

        $this->assertSame('application/json', $mockClient->lastRequest->getHeaderLine('Content-Type'));
        $this->assertJsonStringEqualsJsonString('{"key":"value"}', (string) $mockClient->lastRequest->getBody());
    }

    public function test_returns_decoded_json_body(): void
    {
        $factory    = new Psr17Factory();
        $mockClient = $this->makeMockPsrClient(200, '{"data":{"id":"doc_1","title":"T"}}');
        $transport  = new PsrHttpClient($mockClient, $factory, $factory);

        [$status, $body] = $transport->send('GET', 'https://api.test/path');

        $this->assertSame(200, $status);
        $this->assertSame('doc_1', $body['data']['id']);
    }

    public function test_send_raw_returns_bytes(): void
    {
        $factory    = new Psr17Factory();
        $mockClient = $this->makeMockPsrClient(200, '%PDF-1.4 fake');
        $transport  = new PsrHttpClient($mockClient, $factory, $factory);

        $bytes = $transport->sendRaw('GET', 'https://api.test/pdf');

        $this->assertSame('%PDF-1.4 fake', $bytes);
    }
}
