<?php

declare(strict_types=1);

namespace Signori\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Signori\Exceptions\NotFoundException;
use Signori\Responses\DocumentResponse;
use Signori\Responses\SignerResponse;
use Signori\Signori;

/**
 * Integration tests — hit the real API.
 *
 * These tests are SKIPPED automatically unless SIGNORI_API_KEY is set.
 *
 * NOTE: The Signori API currently authenticates via JWT bearer tokens on
 * document endpoints. Set SIGNORI_API_KEY to a JWT access token obtained
 * from POST /api/v1/auth/login. API key bearer auth will be supported once
 * the backend wires API key lookup into the auth middleware.
 *
 * Run against local dev:
 *   SIGNORI_API_KEY=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
 *     -H "Content-Type: application/json" \
 *     -d '{"email":"you@example.com","password":"yourpassword"}' \
 *     | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['access_token'])") \
 *   SIGNORI_BASE_URL=http://localhost:8000 \
 *   vendor/bin/phpunit --testsuite Integration
 *
 * Run against staging:
 *   SIGNORI_API_KEY=<jwt> SIGNORI_BASE_URL=https://api-staging.kyhome.co.in \
 *     vendor/bin/phpunit --testsuite Integration
 *
 * Each test cleans up after itself (void the created documents).
 */
final class DocumentsIntegrationTest extends TestCase
{
    private static Signori $sv;

    public static function setUpBeforeClass(): void
    {
        $apiKey  = getenv('SIGNORI_API_KEY');
        $baseUrl = getenv('SIGNORI_BASE_URL') ?: 'http://localhost:8000';

        if (! $apiKey) {
            return;
        }

        self::$sv = Signori::client((string) $apiKey, $baseUrl);
    }

    protected function setUp(): void
    {
        if (! getenv('SIGNORI_API_KEY')) {
            $this->markTestSkipped('SIGNORI_API_KEY not set — skipping integration tests.');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a minimal valid single-page PDF in memory.
     * No file I/O, no dependencies.
     */
    private function minimalPdf(): string
    {
        return "%PDF-1.4\n"
            . "1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj\n"
            . "2 0 obj\n<</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj\n"
            . "3 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]>>\nendobj\n"
            . "xref\n0 4\n"
            . "0000000000 65535 f \n"
            . "0000000009 00000 n \n"
            . "0000000058 00000 n \n"
            . "0000000115 00000 n \n"
            . "trailer\n<</Size 4 /Root 1 0 R>>\n"
            . "startxref\n190\n%%EOF";
    }

    private function uploadTestDocument(string $title = 'SDK Integration Test'): DocumentResponse
    {
        return self::$sv->documents->upload($this->minimalPdf(), $title, 'generic');
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    public function test_upload_creates_document(): void
    {
        $doc = $this->uploadTestDocument('Integration: Upload Test');

        $this->assertNotEmpty($doc->id);
        $this->assertSame('Integration: Upload Test', $doc->title);
        $this->assertSame('draft', $doc->status);

        self::$sv->documents->void($doc->id);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function test_list_returns_documents(): void
    {
        $page = self::$sv->documents->list(['limit' => 5]);

        $this->assertNotNull($page);
        foreach ($page->items as $doc) {
            $this->assertInstanceOf(DocumentResponse::class, $doc);
            $this->assertNotEmpty($doc->id);
        }
    }

    // ── Get ───────────────────────────────────────────────────────────────────

    public function test_get_returns_uploaded_document(): void
    {
        $created = $this->uploadTestDocument('Integration: Get Test');

        $fetched = self::$sv->documents->get($created->id);

        $this->assertSame($created->id, $fetched->id);
        $this->assertSame('Integration: Get Test', $fetched->title);

        self::$sv->documents->void($created->id);
    }

    public function test_get_throws_not_found_for_unknown_id(): void
    {
        $this->expectException(NotFoundException::class);
        self::$sv->documents->get('doc_does_not_exist_xyz_999');
    }

    // ── Add signer ────────────────────────────────────────────────────────────

    public function test_add_signer_to_document(): void
    {
        $doc    = $this->uploadTestDocument('Integration: Signer Test');
        $signer = self::$sv->signers->add(
            $doc->id,
            'integration-test@signori-sdk.invalid',
            'SDK Test Signer',
        );

        $this->assertInstanceOf(SignerResponse::class, $signer);
        $this->assertSame('integration-test@signori-sdk.invalid', $signer->email);
        $this->assertSame('pending', $signer->status);

        self::$sv->documents->void($doc->id);
    }

    // ── Audit trail ───────────────────────────────────────────────────────────

    public function test_audit_trail_has_at_least_created_event(): void
    {
        $doc   = $this->uploadTestDocument('Integration: Audit Test');
        $trail = self::$sv->documents->auditTrail($doc->id);

        $this->assertSame($doc->id, $trail->documentId);
        $this->assertNotEmpty($trail->events);

        $types = array_column($trail->events, 'event_type');
        $this->assertContains('document.created', $types);

        self::$sv->documents->void($doc->id);
    }

    // ── Void ──────────────────────────────────────────────────────────────────

    public function test_void_sets_status_to_voided(): void
    {
        $doc    = $this->uploadTestDocument('Integration: Void Test');
        $voided = self::$sv->documents->void($doc->id, 'SDK test cleanup');

        $this->assertSame('voided', $voided->status);
    }
}
