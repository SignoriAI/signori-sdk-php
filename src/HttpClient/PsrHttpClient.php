<?php

declare(strict_types=1);

namespace SignVault\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * PSR-18 adapter — lets you plug in Guzzle, Symfony HttpClient, etc.
 *
 * Usage with Guzzle:
 *
 *   use GuzzleHttp\Client;
 *   use GuzzleHttp\Psr7\HttpFactory;
 *   use SignVault\HttpClient\PsrHttpClient;
 *
 *   $factory = new HttpFactory();
 *   $transport = new PsrHttpClient(new Client(), $factory, $factory);
 *   $sv = SignVault::client()->withHttpClient($transport);
 */
final class PsrHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface         $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
    ) {}

    /** {@inheritdoc} */
    public function send(
        string $method,
        string $url,
        array  $headers   = [],
        array  $json      = [],
        array  $multipart = [],
    ): array {
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($json !== []) {
            $body    = json_encode($json, JSON_THROW_ON_ERROR);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        // Note: multipart is more involved with PSR-7; for complex file uploads
        // fall back to CurlHttpClient or implement your own multipart builder.

        $response   = $this->client->sendRequest($request);
        $status     = $response->getStatusCode();
        $rawBody    = (string) $response->getBody();
        $decoded    = json_decode($rawBody, true);

        return [$status, is_array($decoded) ? $decoded : ['raw' => $rawBody]];
    }

    /** {@inheritdoc} */
    public function sendRaw(string $method, string $url, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return (string) $this->client->sendRequest($request)->getBody();
    }
}
