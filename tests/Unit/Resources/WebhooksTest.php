<?php

declare(strict_types=1);

namespace SignVault\Tests\Unit\Resources;

use SignVault\Resources\Webhooks;
use SignVault\Responses\PaginatedResponse;
use SignVault\Responses\WebhookResponse;
use SignVault\Tests\UnitTestCase;

final class WebhooksTest extends UnitTestCase
{
    public function test_create_posts_url_and_events(): void
    {
        $this->http->enqueue(200, $this->envelope($this->webhookFixture()));

        $this->sv->webhooks->create(
            'https://example.com/webhook',
            ['document.completed', 'signer.signed'],
        );

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('/api/v1/webhooks', $req['url']);
        $this->assertSame('https://example.com/webhook', $req['json']['url']);
        $this->assertSame(['document.completed', 'signer.signed'], $req['json']['events']);
    }

    public function test_create_returns_webhook_response(): void
    {
        $this->http->enqueue(200, $this->envelope($this->webhookFixture()));

        $wh = $this->sv->webhooks->create('https://example.com/wh', ['document.completed']);

        $this->assertInstanceOf(WebhookResponse::class, $wh);
        $this->assertSame('wh_abc123', $wh->id);
        $this->assertTrue($wh->isActive);
    }

    public function test_list_returns_paginated_response(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([
            $this->webhookFixture(['id' => 'wh_1']),
            $this->webhookFixture(['id' => 'wh_2']),
        ]));

        $page = $this->sv->webhooks->list();

        $this->assertInstanceOf(PaginatedResponse::class, $page);
        $this->assertCount(2, $page->items);
    }

    public function test_delete_sends_delete_request(): void
    {
        $this->http->enqueue(200, []);

        $this->sv->webhooks->delete('wh_abc123');

        $req = $this->http->lastRequest();
        $this->assertSame('DELETE', $req['method']);
        $this->assertStringContainsString('wh_abc123', $req['url']);
    }

    public function test_test_sends_post_to_test_endpoint(): void
    {
        $this->http->enqueue(200, ['delivered' => true]);

        $result = $this->sv->webhooks->test('wh_abc123');

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('wh_abc123/test', $req['url']);
    }

    // ── Static signature verification ─────────────────────────────────────────

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $secret  = 'wh_secret_xyz';
        $payload = '{"event":"document.completed","data":{"id":"doc_1"}}';
        $sig     = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(Webhooks::verify($payload, $sig, $secret));
    }

    public function test_verify_accepts_sha256_prefixed_signature(): void
    {
        $secret  = 'wh_secret_xyz';
        $payload = '{"event":"document.completed"}';
        $sig     = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(Webhooks::verify($payload, $sig, $secret));
    }

    public function test_verify_returns_false_for_wrong_secret(): void
    {
        $payload = '{"event":"document.completed"}';
        $sig     = hash_hmac('sha256', $payload, 'correct-secret');

        $this->assertFalse(Webhooks::verify($payload, $sig, 'wrong-secret'));
    }

    public function test_verify_returns_false_for_tampered_payload(): void
    {
        $secret  = 'wh_secret_xyz';
        $sig     = hash_hmac('sha256', '{"event":"document.completed"}', $secret);

        $this->assertFalse(Webhooks::verify('{"event":"document.voided"}', $sig, $secret));
    }

    public function test_verify_returns_false_for_empty_inputs(): void
    {
        $this->assertFalse(Webhooks::verify('', 'sig', 'secret'));
        $this->assertFalse(Webhooks::verify('payload', '', 'secret'));
        $this->assertFalse(Webhooks::verify('payload', 'sig', ''));
    }

    public function test_construct_event_parses_payload(): void
    {
        $payload = json_encode([
            'event' => 'document.completed',
            'data'  => ['id' => 'doc_1', 'status' => 'completed'],
        ]);

        $event = Webhooks::constructEvent($payload);

        $this->assertSame('document.completed', $event['event']);
        $this->assertSame('doc_1', $event['data']['id']);
    }

    public function test_construct_event_throws_on_invalid_json(): void
    {
        $this->expectException(\SignVault\Exceptions\SignVaultException::class);
        Webhooks::constructEvent('not-json');
    }
}
