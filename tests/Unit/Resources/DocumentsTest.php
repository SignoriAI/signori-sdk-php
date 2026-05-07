<?php

declare(strict_types=1);

namespace Signori\Tests\Unit\Resources;

use Signori\Responses\DocumentFieldResponse;
use Signori\Responses\DocumentResponse;
use Signori\Responses\PaginatedResponse;
use Signori\Responses\AuditTrailResponse;
use Signori\Tests\UnitTestCase;

final class DocumentsTest extends UnitTestCase
{
    // ── upload ────────────────────────────────────────────────────────────────

    public function test_upload_sends_post_to_documents_endpoint(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));

        // Upload raw bytes (no real file needed in unit tests)
        $doc = $this->sv->documents->upload('%PDF-1.4 fake', 'My Agreement');

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('/api/v1/documents', $req['url']);
    }

    public function test_upload_sends_title_as_query_param(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));

        $this->sv->documents->upload('%PDF-1.4 fake', 'NDA 2026', 'nda');

        $url = $this->http->lastRequest()['url'];
        $this->assertStringContainsString('title=NDA+2026', $url);
        $this->assertStringContainsString('document_type=nda', $url);
    }

    public function test_upload_returns_document_response(): void
    {
        $fixture = $this->documentFixture(['title' => 'NDA 2026', 'status' => 'draft']);
        $this->http->enqueue(200, $this->envelope($fixture));

        $doc = $this->sv->documents->upload('%PDF-1.4 fake', 'NDA 2026', 'nda');

        $this->assertInstanceOf(DocumentResponse::class, $doc);
        $this->assertSame('doc_abc123', $doc->id);
        $this->assertSame('NDA 2026', $doc->title);
        $this->assertSame('draft', $doc->status);
        $this->assertSame('generic', $doc->documentType); // fixture default
    }

    // ── list ──────────────────────────────────────────────────────────────────

    public function test_list_returns_paginated_response(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([
            $this->documentFixture(['id' => 'doc_1', 'title' => 'Doc 1']),
            $this->documentFixture(['id' => 'doc_2', 'title' => 'Doc 2']),
        ]));

        $page = $this->sv->documents->list();

        $this->assertInstanceOf(PaginatedResponse::class, $page);
        $this->assertCount(2, $page->items);
        $this->assertInstanceOf(DocumentResponse::class, $page->first());
        $this->assertSame('doc_1', $page->first()->id);
    }

    public function test_list_passes_filters_as_query_params(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([]));

        $this->sv->documents->list(['status' => 'completed', 'limit' => 5]);

        $url = $this->http->lastRequest()['url'];
        $this->assertStringContainsString('status=completed', $url);
        $this->assertStringContainsString('limit=5', $url);
    }

    public function test_list_is_empty_when_no_documents(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([]));

        $page = $this->sv->documents->list();

        $this->assertTrue($page->isEmpty());
        $this->assertFalse($page->hasMore());
    }

    public function test_list_has_more_when_next_cursor_present(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope(
            [$this->documentFixture()],
            10,
            'cursor_xyz'
        ));

        $page = $this->sv->documents->list();

        $this->assertTrue($page->hasMore());
        $this->assertSame('cursor_xyz', $page->nextCursor);
    }

    // ── get ───────────────────────────────────────────────────────────────────

    public function test_get_builds_correct_url(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture(['id' => 'doc_xyz'])));

        $this->sv->documents->get('doc_xyz');

        $this->assertStringContainsString('/api/v1/documents/doc_xyz', $this->http->lastRequest()['url']);
    }

    public function test_get_returns_document_response(): void
    {
        $fixture = $this->documentFixture(['id' => 'doc_xyz', 'status' => 'completed']);
        $this->http->enqueue(200, $this->envelope($fixture));

        $doc = $this->sv->documents->get('doc_xyz');

        $this->assertSame('doc_xyz', $doc->id);
        $this->assertSame('completed', $doc->status);
    }

    // ── send ──────────────────────────────────────────────────────────────────

    public function test_send_posts_signers_array(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture(['status' => 'pending'])));

        $this->sv->documents->send('doc_abc123', [
            ['email' => 'alice@example.com', 'full_name' => 'Alice Smith'],
            ['email' => 'bob@example.com',   'full_name' => 'Bob Jones'],
        ]);

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('doc_abc123/send', $req['url']);
        $this->assertCount(2, $req['json']['signers']);
    }

    public function test_send_includes_optional_message(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));

        $this->sv->documents->send(
            'doc_abc123',
            [['email' => 'a@b.com', 'full_name' => 'A B']],
            message: 'Please sign at your earliest convenience.',
        );

        $this->assertSame(
            'Please sign at your earliest convenience.',
            $this->http->lastRequest()['json']['message']
        );
    }

    // ── void ──────────────────────────────────────────────────────────────────

    public function test_void_sends_post_to_void_endpoint(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture(['status' => 'voided'])));

        $doc = $this->sv->documents->void('doc_abc123', 'Duplicate sent');

        $req = $this->http->lastRequest();
        $this->assertStringContainsString('doc_abc123/void', $req['url']);
        $this->assertSame('Duplicate sent', $req['json']['reason']);
        $this->assertSame('voided', $doc->status);
    }

    // ── audit trail ───────────────────────────────────────────────────────────

    public function test_audit_trail_returns_audit_trail_response(): void
    {
        $this->http->enqueue(200, $this->envelope([
            'document_id' => 'doc_abc123',
            'chain_valid' => true,
            'events'      => [
                ['event_type' => 'document.created', 'actor_email' => 'owner@co.com'],
                ['event_type' => 'signer.signed',    'actor_email' => 'alice@example.com'],
            ],
        ]));

        $trail = $this->sv->documents->auditTrail('doc_abc123');

        $this->assertInstanceOf(AuditTrailResponse::class, $trail);
        $this->assertSame('doc_abc123', $trail->documentId);
        $this->assertTrue($trail->chainValid);
        $this->assertCount(2, $trail->events);
    }

    // ── download ──────────────────────────────────────────────────────────────

    public function test_download_sends_get_to_download_endpoint(): void
    {
        $this->http->enqueue(200, ['_raw' => '%PDF-1.4 fake-bytes']);

        $this->sv->documents->download('doc_abc123');

        $req = $this->http->lastRequest();
        $this->assertSame('GET', $req['method']);
        $this->assertStringContainsString('doc_abc123/download', $req['url']);
    }

    // ── place fields ──────────────────────────────────────────────────────────

    public function test_place_fields_puts_to_fields_endpoint(): void
    {
        $this->http->enqueue(200, $this->envelope([
            'fields' => [
                $this->documentFieldFixture(['page' => 1]),
                $this->documentFieldFixture(['page' => 2, 'id' => 'fld_2']),
            ],
        ]));

        $fields = $this->sv->documents->placeFields('doc_abc123', [
            ['field_type' => 'signature', 'assigned_to' => 'signer_1', 'page' => 1, 'x' => 60, 'y' => 700, 'width' => 240, 'height' => 50],
            ['field_type' => 'signature', 'assigned_to' => 'signer_1', 'page' => 2, 'x' => 60, 'y' => 700, 'width' => 240, 'height' => 50],
        ]);

        $req = $this->http->lastRequest();
        $this->assertSame('PUT', $req['method']);
        $this->assertStringContainsString('doc_abc123/fields', $req['url']);
        $this->assertCount(2, $req['json']['fields']);
        $this->assertSame('signature', $req['json']['fields'][0]['field_type']);

        $this->assertCount(2, $fields);
        $this->assertContainsOnlyInstancesOf(DocumentFieldResponse::class, $fields);
        $this->assertSame(1, $fields[0]->page);
        $this->assertSame(2, $fields[1]->page);
        $this->assertSame('signer_1', $fields[0]->assignedTo);
    }

    // ── toArray ───────────────────────────────────────────────────────────────

    public function test_to_array_returns_original_data(): void
    {
        $fixture = $this->documentFixture();
        $this->http->enqueue(200, $this->envelope($fixture));

        $doc = $this->sv->documents->get('doc_abc123');

        $this->assertSame($fixture['id'], $doc->toArray()['id']);
    }
}
