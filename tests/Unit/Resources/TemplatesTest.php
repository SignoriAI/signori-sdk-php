<?php

declare(strict_types=1);

namespace Signori\Tests\Unit\Resources;

use Signori\Responses\DocumentResponse;
use Signori\Responses\PaginatedResponse;
use Signori\Responses\TemplateResponse;
use Signori\Tests\UnitTestCase;

final class TemplatesTest extends UnitTestCase
{
    private function templateFixture(array $overrides = []): array
    {
        return array_merge([
            'id'            => 'tpl_abc123',
            'name'          => 'Standard NDA',
            'description'   => 'Mutual non-disclosure agreement',
            'document_type' => 'nda',
            'is_system'     => false,
            'fields'        => [
                ['name' => 'party_name', 'type' => 'text'],
                ['name' => 'effective_date', 'type' => 'date'],
            ],
            'created_at'    => '2026-01-01T00:00:00Z',
        ], $overrides);
    }

    public function test_list_returns_paginated_templates(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([
            $this->templateFixture(['id' => 'tpl_1']),
            $this->templateFixture(['id' => 'tpl_2', 'is_system' => true]),
        ]));

        $page = $this->sv->templates->list();

        $this->assertInstanceOf(PaginatedResponse::class, $page);
        $this->assertCount(2, $page->items);
        $this->assertInstanceOf(TemplateResponse::class, $page->first());
    }

    public function test_get_returns_template_response(): void
    {
        $fixture = $this->templateFixture(['name' => 'Employment Contract']);
        $this->http->enqueue(200, $this->envelope($fixture));

        $tpl = $this->sv->templates->get('tpl_abc123');

        $this->assertInstanceOf(TemplateResponse::class, $tpl);
        $this->assertSame('Employment Contract', $tpl->name);
        $this->assertFalse($tpl->isSystem);
        $this->assertCount(2, $tpl->fields);
    }

    public function test_create_document_posts_field_values_and_signers(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));

        $this->sv->templates->createDocument(
            'tpl_abc123',
            fieldValues: ['party_name' => 'Acme Corp', 'effective_date' => '2026-06-01'],
            signers:     [['email' => 'ceo@acme.com', 'full_name' => 'Jane CEO']],
        );

        $req = $this->http->lastRequest();
        $this->assertSame('POST', $req['method']);
        $this->assertStringContainsString('tpl_abc123/documents', $req['url']);
        $this->assertSame('Acme Corp', $req['json']['field_values']['party_name']);
        $this->assertCount(1, $req['json']['signers']);
    }

    public function test_create_document_returns_document_response(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));

        $doc = $this->sv->templates->createDocument('tpl_abc123');

        $this->assertInstanceOf(DocumentResponse::class, $doc);
    }
}
