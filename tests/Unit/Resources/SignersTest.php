<?php

declare(strict_types=1);

namespace SignVault\Tests\Unit\Resources;

use SignVault\Responses\SignerResponse;
use SignVault\Tests\UnitTestCase;

final class SignersTest extends UnitTestCase
{
    public function test_add_posts_to_signers_endpoint(): void
    {
        $this->http->enqueue(200, $this->envelope($this->signerFixture()));

        $this->sv->signers->add('doc_abc123', 'alice@example.com', 'Alice Smith');

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('doc_abc123/signers', $req['url']);
    }

    public function test_add_sends_all_fields(): void
    {
        $this->http->enqueue(200, $this->envelope($this->signerFixture([
            'role'        => 'approver',
            'auth_method' => 'sms_otp',
            'phone'       => '+14155550100',
        ])));

        $this->sv->signers->add(
            documentId:   'doc_abc123',
            email:        'alice@example.com',
            fullName:     'Alice Smith',
            role:         'approver',
            authMethod:   'sms_otp',
            phone:        '+14155550100',
            signingOrder: 2,
        );

        $body = $this->http->lastRequest()['json'];
        $this->assertSame('approver', $body['role']);
        $this->assertSame('sms_otp', $body['auth_method']);
        $this->assertSame('+14155550100', $body['phone']);
        $this->assertSame(2, $body['signing_order']);
    }

    public function test_add_returns_signer_response(): void
    {
        $fixture = $this->signerFixture(['email' => 'bob@test.com', 'full_name' => 'Bob Test']);
        $this->http->enqueue(200, $this->envelope($fixture));

        $signer = $this->sv->signers->add('doc_abc123', 'bob@test.com', 'Bob Test');

        $this->assertInstanceOf(SignerResponse::class, $signer);
        $this->assertSame('bob@test.com', $signer->email);
        $this->assertSame('Bob Test', $signer->fullName);
        $this->assertSame('pending', $signer->status);
    }

    public function test_add_omits_null_phone(): void
    {
        $this->http->enqueue(200, $this->envelope($this->signerFixture()));

        $this->sv->signers->add('doc_abc123', 'a@b.com', 'A B');

        $body = $this->http->lastRequest()['json'];
        $this->assertArrayNotHasKey('phone', $body);
    }

    public function test_remind_posts_to_remind_endpoint(): void
    {
        $this->http->enqueue(200, $this->envelope($this->signerFixture()));

        $this->sv->signers->remind('doc_abc123', 'sig_abc123');

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('sig_abc123/remind', $req['url']);
    }

    public function test_remove_sends_delete(): void
    {
        $this->http->enqueue(200, []);

        $this->sv->signers->remove('doc_abc123', 'sig_abc123');

        $this->assertSame('DELETE', $this->http->lastRequest()['method']);
    }

    public function test_signer_response_maps_signed_at(): void
    {
        $fixture = $this->signerFixture(['signed_at' => '2026-03-01T12:00:00Z', 'status' => 'signed']);
        $this->http->enqueue(200, $this->envelope($fixture));

        $signer = $this->sv->signers->get('doc_abc123', 'sig_abc123');

        $this->assertSame('signed', $signer->status);
        $this->assertSame('2026-03-01T12:00:00Z', $signer->signedAt);
        $this->assertNull($signer->declinedAt);
    }
}
