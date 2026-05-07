<?php

declare(strict_types=1);

namespace Signori\Tests;

use PHPUnit\Framework\TestCase;
use Signori\Signori;

/**
 * Base class for unit tests — provides a pre-wired client + mock transport.
 */
abstract class UnitTestCase extends TestCase
{
    protected MockHttpClient $http;
    protected Signori $sv;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->sv   = Signori::client('test-key-unit', 'https://api.test')
            ->withHttpClient($this->http);
    }

    /** Wrap a response body in the standard Signori envelope. */
    protected function envelope(array $data, ?string $requestId = null): array
    {
        return ['data' => $data, 'request_id' => $requestId ?? 'req_test'];
    }

    /** Wrap a list of items in the paginated envelope. */
    protected function paginatedEnvelope(array $items, int $total = 0, ?string $nextCursor = null): array
    {
        return $this->envelope([
            'items'       => $items,
            'total'       => $total ?: count($items),
            'next_cursor' => $nextCursor,
            'prev_cursor' => null,
        ]);
    }

    protected function documentFixture(array $overrides = []): array
    {
        return array_merge([
            'id'            => 'doc_abc123',
            'title'         => 'Test Agreement',
            'status'        => 'draft',
            'document_type' => 'generic',
            'page_count'    => 3,
            'description'   => null,
            'template_id'   => null,
            'created_at'    => '2026-01-01T00:00:00Z',
            'completed_at'  => null,
            'expires_at'    => null,
        ], $overrides);
    }

    protected function documentFieldFixture(array $overrides = []): array
    {
        return array_merge([
            'id'          => 'fld_1',
            'document_id' => 'doc_abc123',
            'field_type'  => 'signature',
            'assigned_to' => 'signer_1',
            'page'        => 1,
            'x'           => 60.0,
            'y'           => 700.0,
            'width'       => 240.0,
            'height'      => 50.0,
            'required'    => true,
        ], $overrides);
    }

    protected function signerFixture(array $overrides = []): array
    {
        return array_merge([
            'id'            => 'sig_abc123',
            'document_id'   => 'doc_abc123',
            'email'         => 'alice@example.com',
            'full_name'     => 'Alice Smith',
            'phone'         => null,
            'role'          => 'signer',
            'status'        => 'pending',
            'signing_order' => 1,
            'auth_method'   => 'email',
            'signed_at'     => null,
            'declined_at'   => null,
            'created_at'    => '2026-01-01T00:00:00Z',
        ], $overrides);
    }

    protected function webhookFixture(array $overrides = []): array
    {
        return array_merge([
            'id'         => 'wh_abc123',
            'url'        => 'https://example.com/webhook',
            'events'     => ['document.completed', 'signer.signed'],
            'is_active'  => true,
            'created_at' => '2026-01-01T00:00:00Z',
        ], $overrides);
    }
}
