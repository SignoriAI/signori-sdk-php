<?php

declare(strict_types=1);

namespace Signori\Tests\Unit\Resources;

use Signori\Tests\UnitTestCase;

final class ApiKeysTest extends UnitTestCase
{
    public function test_create_omits_scopes_by_default(): void
    {
        $this->http->enqueue(200, $this->envelope(['id' => 'key_1', 'name' => 'CI']));

        $this->sv->apiKeys->create('CI');

        $this->assertSame(['name' => 'CI'], $this->http->lastRequest()['json']);
    }

    public function test_create_sends_scopes(): void
    {
        $this->http->enqueue(200, $this->envelope(['id' => 'key_1', 'name' => 'CI']));

        $this->sv->apiKeys->create('CI', ['read', 'write']);

        $this->assertSame(['name' => 'CI', 'scopes' => ['read', 'write']], $this->http->lastRequest()['json']);
    }

    public function test_list_passes_pagination(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([]));

        $this->sv->apiKeys->list(10, 'key_5');

        $this->assertSame('https://api.test/api/v1/api-keys?limit=10&cursor=key_5', $this->http->lastRequest()['url']);
    }
}
