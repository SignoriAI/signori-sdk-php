<?php

declare(strict_types=1);

namespace Signori\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Signori\Resources\ApiKeys;
use Signori\Resources\Documents;
use Signori\Resources\IframeOrigins;
use Signori\Resources\Signers;
use Signori\Resources\Templates;
use Signori\Resources\Webhooks;
use Signori\Signori;
use Signori\Tests\UnitTestCase;

/**
 * Pins every SDK call to a route the Signori API actually serves.
 *
 * API_ROUTES mirrors the routers in signori-api (signori/api/v1/*.py, mounted
 * under /api/v1). When the API adds, moves or removes a route the SDK uses,
 * update this table first — the SDK calls are then checked against it.
 */
final class RouteTableTest extends UnitTestCase
{
    private const API_ROUTES = [
        // documents.py
        'POST /api/v1/documents',
        'GET /api/v1/documents',
        'GET /api/v1/documents/{doc_id}',
        'PATCH /api/v1/documents/{doc_id}',
        'DELETE /api/v1/documents/{doc_id}',
        'POST /api/v1/documents/{doc_id}/send',
        'GET /api/v1/documents/{doc_id}/download-original',
        'GET /api/v1/documents/{doc_id}/download',
        'GET /api/v1/documents/{doc_id}/download/signed',
        'GET /api/v1/documents/{doc_id}/audit-trail',
        // document_fields.py
        'GET /api/v1/documents/{document_id}/fields',
        'PUT /api/v1/documents/{document_id}/fields',
        // signers.py
        'POST /api/v1/documents/{doc_id}/signers',
        'GET /api/v1/documents/{doc_id}/signers',
        'DELETE /api/v1/documents/{doc_id}/signers/{signer_id}',
        'POST /api/v1/documents/{doc_id}/signers/{signer_id}/remind',
        // templates.py
        'GET /api/v1/templates',
        'GET /api/v1/templates/{template_id}',
        'POST /api/v1/templates/{template_id}/prefill',
        'POST /api/v1/templates/{template_id}/documents',
        // webhooks.py
        'POST /api/v1/webhooks',
        'GET /api/v1/webhooks',
        'PATCH /api/v1/webhooks/{webhook_id}',
        'DELETE /api/v1/webhooks/{webhook_id}',
        'POST /api/v1/webhooks/{webhook_id}/test',
        // api_keys.py
        'POST /api/v1/api-keys',
        'GET /api/v1/api-keys',
        'DELETE /api/v1/api-keys/{key_id}',
        // iframe_origins.py
        'POST /api/v1/iframe-origins',
        'GET /api/v1/iframe-origins',
        'DELETE /api/v1/iframe-origins/{origin_id}',
    ];

    private const RESOURCES = [
        Documents::class,
        Signers::class,
        Templates::class,
        Webhooks::class,
        ApiKeys::class,
        IframeOrigins::class,
    ];

    /**
     * @return array<string, array{
     *     0: callable(Signori): mixed,
     *     1: list<array<string, mixed>>,
     *     2: list<array{0: string, 1: string, 2: array<string, string>}>,
     * }>
     */
    public static function sdkCalls(): array
    {
        $doc      = ['id' => 'doc_1', 'title' => 'T', 'status' => 'draft'];
        $signer   = ['id' => 'sig_1', 'document_id' => 'doc_1', 'email' => 'a@b.c', 'full_name' => 'A'];
        $page     = static fn (array $items) => ['data' => ['items' => $items, 'total' => count($items), 'next_cursor' => null]];
        $one      = static fn (array $data) => ['data' => $data];
        $raw      = ['_raw' => '%PDF-1.4'];
        $template = ['id' => 'tpl_1', 'name' => 'T'];
        $webhook  = ['id' => 'wh_1', 'url' => 'https://x.test', 'events' => []];

        return [
            'Documents::upload' => [
                fn (Signori $sv) => $sv->documents->upload('%PDF-1.4', 'NDA', 'nda'),
                [$one($doc)],
                [['POST', '/api/v1/documents', ['title' => 'NDA', 'document_type' => 'nda']]],
            ],
            'Documents::list' => [
                fn (Signori $sv) => $sv->documents->list(['status' => 'completed', 'limit' => 5]),
                [$page([$doc])],
                [['GET', '/api/v1/documents', ['status' => 'completed', 'limit' => '5']]],
            ],
            'Documents::get' => [
                fn (Signori $sv) => $sv->documents->get('doc_1'),
                [$one($doc)],
                [['GET', '/api/v1/documents/doc_1', []]],
            ],
            'Documents::send' => [
                fn (Signori $sv) => $sv->documents->send('doc_1', [['email' => 'a@b.c', 'full_name' => 'A']]),
                [$one($doc)],
                [['POST', '/api/v1/documents/doc_1/send', []]],
            ],
            'Documents::void' => [
                fn (Signori $sv) => $sv->documents->void('doc_1', 'Wrong recipient'),
                [$one($doc)],
                [['DELETE', '/api/v1/documents/doc_1', ['reason' => 'Wrong recipient']]],
            ],
            'Documents::downloadOriginal' => [
                fn (Signori $sv) => $sv->documents->downloadOriginal('doc_1'),
                [$raw],
                [['GET', '/api/v1/documents/doc_1/download-original', []]],
            ],
            'Documents::downloadSigned' => [
                fn (Signori $sv) => $sv->documents->downloadSigned('doc_1'),
                [$raw],
                [['GET', '/api/v1/documents/doc_1/download/signed', []]],
            ],
            'Documents::download' => [
                fn (Signori $sv) => $sv->documents->download('doc_1'),
                [$raw],
                [['GET', '/api/v1/documents/doc_1/download', []]],
            ],
            'Documents::auditTrail' => [
                fn (Signori $sv) => $sv->documents->auditTrail('doc_1'),
                [$one(['document_id' => 'doc_1', 'events' => []])],
                [['GET', '/api/v1/documents/doc_1/audit-trail', []]],
            ],
            'Documents::signers' => [
                fn (Signori $sv) => $sv->documents->signers('doc_1'),
                [$page([$signer])],
                [['GET', '/api/v1/documents/doc_1/signers', ['limit' => '100']]],
            ],
            'Documents::placeFields' => [
                fn (Signori $sv) => $sv->documents->placeFields('doc_1', [
                    ['field_type' => 'signature', 'page' => 1, 'x' => 1, 'y' => 1, 'width' => 1, 'height' => 1],
                ]),
                [$one(['fields' => []])],
                [['PUT', '/api/v1/documents/doc_1/fields', []]],
            ],
            'Signers::add' => [
                fn (Signori $sv) => $sv->signers->add('doc_1', 'a@b.c', 'A'),
                [$one($signer)],
                [['POST', '/api/v1/documents/doc_1/signers', []]],
            ],
            'Signers::list' => [
                fn (Signori $sv) => $sv->signers->list('doc_1'),
                [$page([$signer])],
                [['GET', '/api/v1/documents/doc_1/signers', ['limit' => '100']]],
            ],
            'Signers::get' => [
                fn (Signori $sv) => $sv->signers->get('doc_1', 'sig_1'),
                [$page([$signer])],
                [['GET', '/api/v1/documents/doc_1/signers', ['limit' => '100']]],
            ],
            'Signers::remind' => [
                fn (Signori $sv) => $sv->signers->remind('doc_1', 'sig_1'),
                [$one($signer)],
                [['POST', '/api/v1/documents/doc_1/signers/sig_1/remind', []]],
            ],
            'Signers::remove' => [
                fn (Signori $sv) => $sv->signers->remove('doc_1', 'sig_1'),
                [$one(['deleted' => true])],
                [['DELETE', '/api/v1/documents/doc_1/signers/sig_1', []]],
            ],
            'Templates::list' => [
                fn (Signori $sv) => $sv->templates->list(),
                [$page([$template])],
                [['GET', '/api/v1/templates', ['limit' => '50']]],
            ],
            'Templates::get' => [
                fn (Signori $sv) => $sv->templates->get('tpl_1'),
                [$one($template)],
                [['GET', '/api/v1/templates/tpl_1', []]],
            ],
            'Templates::createDocument' => [
                fn (Signori $sv) => $sv->templates->createDocument('tpl_1'),
                [$one($doc)],
                [['POST', '/api/v1/templates/tpl_1/documents', []]],
            ],
            'Templates::prefillFromContact' => [
                fn (Signori $sv) => $sv->templates->prefillFromContact('tpl_1', 'ct_1'),
                [$one(['field_values' => []])],
                [['POST', '/api/v1/templates/tpl_1/prefill', []]],
            ],
            'Webhooks::create' => [
                fn (Signori $sv) => $sv->webhooks->create('https://x.test', ['document.completed'], 'secret-123'),
                [$one($webhook)],
                [['POST', '/api/v1/webhooks', []]],
            ],
            'Webhooks::list' => [
                fn (Signori $sv) => $sv->webhooks->list(),
                [$page([$webhook])],
                [['GET', '/api/v1/webhooks', ['limit' => '20']]],
            ],
            'Webhooks::update' => [
                fn (Signori $sv) => $sv->webhooks->update('wh_1', ['is_active' => false]),
                [$one($webhook)],
                [['PATCH', '/api/v1/webhooks/wh_1', []]],
            ],
            'Webhooks::delete' => [
                fn (Signori $sv) => $sv->webhooks->delete('wh_1'),
                [$one(['deleted' => true])],
                [['DELETE', '/api/v1/webhooks/wh_1', []]],
            ],
            'Webhooks::test' => [
                fn (Signori $sv) => $sv->webhooks->test('wh_1'),
                [$one(['delivered' => true])],
                [['POST', '/api/v1/webhooks/wh_1/test', []]],
            ],
            'ApiKeys::create' => [
                fn (Signori $sv) => $sv->apiKeys->create('CI'),
                [$one(['id' => 'key_1', 'name' => 'CI'])],
                [['POST', '/api/v1/api-keys', []]],
            ],
            'ApiKeys::list' => [
                fn (Signori $sv) => $sv->apiKeys->list(),
                [$page([])],
                [['GET', '/api/v1/api-keys', ['limit' => '20']]],
            ],
            'ApiKeys::delete' => [
                fn (Signori $sv) => $sv->apiKeys->delete('key_1'),
                [$one(['deleted' => true])],
                [['DELETE', '/api/v1/api-keys/key_1', []]],
            ],
            'IframeOrigins::create' => [
                fn (Signori $sv) => $sv->iframeOrigins->create('https://app.test'),
                [$one(['id' => 'io_1', 'origin' => 'https://app.test'])],
                [['POST', '/api/v1/iframe-origins', []]],
            ],
            'IframeOrigins::list' => [
                fn (Signori $sv) => $sv->iframeOrigins->list(),
                [['data' => []]],
                [['GET', '/api/v1/iframe-origins', []]],
            ],
            'IframeOrigins::delete' => [
                fn (Signori $sv) => $sv->iframeOrigins->delete('io_1'),
                [$one(['deleted' => true])],
                [['DELETE', '/api/v1/iframe-origins/io_1', []]],
            ],
        ];
    }

    /**
     * @param callable(Signori): mixed $call
     * @param list<array<string, mixed>> $responses
     * @param list<array{0: string, 1: string, 2: array<string, string>}> $expected
     */
    #[DataProvider('sdkCalls')]
    public function test_sdk_call_hits_expected_api_route(callable $call, array $responses, array $expected): void
    {
        foreach ($responses as $response) {
            $this->http->enqueue(200, $response);
        }

        $call($this->sv);

        $this->assertSame(count($expected), $this->http->requestCount(), 'Unexpected number of HTTP requests');

        foreach ($expected as $i => [$method, $path, $query]) {
            $request = $this->http->requestAt($i);
            $url     = parse_url($request['url']);
            parse_str($url['query'] ?? '', $actualQuery);

            $this->assertSame('https', $url['scheme']);
            $this->assertSame('api.test', $url['host']);
            $this->assertSame($method, $request['method']);
            $this->assertSame($path, $url['path']);
            $this->assertSame($query, $actualQuery);
            $this->assertTrue(
                self::apiServes($method, $path),
                "{$method} {$path} is not a route in the Signori API route table",
            );
        }
    }

    public function test_every_public_resource_method_is_covered(): void
    {
        $methods = [];
        foreach (self::RESOURCES as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->isConstructor()) {
                    continue;
                }
                $methods[] = $reflection->getShortName() . '::' . $method->getName();
            }
        }

        $covered = array_keys(self::sdkCalls());
        sort($methods);
        sort($covered);

        $this->assertSame($methods, $covered, 'Add every new SDK resource method to sdkCalls()');
    }

    public function test_route_table_rejects_unknown_routes(): void
    {
        $this->assertFalse(self::apiServes('POST', '/api/v1/documents/doc_1/void'));
        $this->assertFalse(self::apiServes('GET', '/api/v1/documents/doc_1/signers/sig_1'));
        $this->assertTrue(self::apiServes('DELETE', '/api/v1/documents/doc_1'));
    }

    private static function apiServes(string $method, string $path): bool
    {
        foreach (self::API_ROUTES as $route) {
            [$routeMethod, $template] = explode(' ', $route, 2);
            $pattern = '#^' . preg_replace('#\\\\\{[a-z_]+\\\\\}#', '[^/]+', preg_quote($template, '#')) . '$#';
            if ($routeMethod === $method && preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
