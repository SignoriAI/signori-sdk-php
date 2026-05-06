<?php

declare(strict_types=1);

namespace SignVault\Resources;

use SignVault\Responses\AuditTrailResponse;
use SignVault\Responses\DocumentFieldResponse;
use SignVault\Responses\DocumentResponse;
use SignVault\Responses\PaginatedResponse;
use SignVault\Responses\SignerResponse;
use SignVault\SignVault;

/**
 * Document operations.
 */
final class Documents
{
    public function __construct(private readonly SignVault $client) {}

    /**
     * Upload a PDF and create a document.
     *
     * @param  string|resource $file  File path, open file handle, or raw PDF bytes.
     * @param  string          $title Document title shown to signers.
     * @param  string          $type  document_type: generic | contract | nda | invoice | …
     */
    public function upload(
        mixed  $file,
        string $title,
        string $type = 'generic',
    ): DocumentResponse {
        $filename = is_string($file) && is_file($file)
            ? basename($file)
            : 'document.pdf';

        // title and document_type are query parameters, not form fields
        $path = '/api/v1/documents?' . http_build_query([
            'title'         => $title,
            'document_type' => $type,
        ]);

        $data = $this->client->upload(
            $path,
            $file,
            $filename,
        );

        return DocumentResponse::from($data);
    }

    /**
     * List documents with optional filters.
     *
     * @param  array{
     *     status?: string,
     *     document_type?: string,
     *     search?: string,
     *     limit?: int,
     *     cursor?: string,
     * } $filters
     */
    public function list(array $filters = []): PaginatedResponse
    {
        $data = $this->client->get('/api/v1/documents', $filters);
        return PaginatedResponse::from($data, fn(array $item) => DocumentResponse::from($item));
    }

    /**
     * Retrieve a single document by ID.
     */
    public function get(string $id): DocumentResponse
    {
        $data = $this->client->get("/api/v1/documents/{$id}");
        return DocumentResponse::from($data);
    }

    /**
     * Send a document for signing.
     *
     * @param  array<int, array{
     *     email: string,
     *     full_name: string,
     *     phone?: string,
     *     role?: string,
     *     signing_order?: int,
     *     auth_method?: string,
     * }> $signers
     */
    public function send(
        string  $id,
        array   $signers,
        ?string $message        = null,
        ?int    $expiryDays     = null,
        bool    $sendReminders  = true,
    ): DocumentResponse {
        $body = array_filter([
            'signers'         => $signers,
            'message'         => $message,
            'expiry_days'     => $expiryDays,
            'send_reminders'  => $sendReminders,
        ], fn($v) => $v !== null);

        $data = $this->client->post("/api/v1/documents/{$id}/send", $body);
        return DocumentResponse::from($data);
    }

    /**
     * Void a document — cancels signing and notifies signers.
     */
    public function void(string $id, ?string $reason = null): DocumentResponse
    {
        $data = $this->client->post(
            "/api/v1/documents/{$id}/void",
            $reason ? ['reason' => $reason] : [],
        );
        return DocumentResponse::from($data);
    }

    /**
     * Download the original (unsigned) PDF as raw bytes.
     */
    public function downloadOriginal(string $id): string
    {
        return $this->client->getBytes("/api/v1/documents/{$id}/download-original");
    }

    /**
     * Download the signed PDF as raw bytes (available after all signers complete).
     */
    public function downloadSigned(string $id): string
    {
        return $this->client->getBytes("/api/v1/documents/{$id}/download/signed");
    }

    /**
     * Download whichever version is latest (signed if available, else original).
     */
    public function download(string $id): string
    {
        return $this->client->getBytes("/api/v1/documents/{$id}/download");
    }

    /**
     * Get the hash-chained audit trail for a document.
     */
    public function auditTrail(string $id): AuditTrailResponse
    {
        $data = $this->client->get("/api/v1/documents/{$id}/audit-trail");
        return AuditTrailResponse::from($data);
    }

    /**
     * List signers on a document.
     *
     * @return SignerResponse[]
     */
    public function signers(string $id): array
    {
        $data = $this->client->get("/api/v1/documents/{$id}/signers");
        $items = is_array($data['items'] ?? null) ? $data['items'] : $data;
        return array_map(fn(array $s) => SignerResponse::from($s), $items);
    }

    /**
     * Replace the field layout on a document — the call is destructive and
     * overwrites any previously-placed fields.
     *
     * Use this to anchor signatures, initials, or other input fields at a
     * known position on the PDF before calling ``send()``. Without it the
     * signing service falls back to a hardcoded position at the top of
     * page 1.
     *
     * Coordinates use PDF points with the origin at the **top-left** of
     * each page (UI convention); the signing service flips them at render
     * time. Standard US Letter is 612x792pt; A4 is 595x842pt.
     *
     * @param  string $documentId
     * @param  array<int, array{
     *     field_type: string,
     *     assigned_to?: string,
     *     page: int,
     *     x: float|int,
     *     y: float|int,
     *     width: float|int,
     *     height: float|int,
     *     required?: bool,
     *     field_label?: string,
     * }> $fields
     * @return list<DocumentFieldResponse>
     */
    public function placeFields(string $documentId, array $fields): array
    {
        $data = $this->client->put(
            "/api/v1/documents/{$documentId}/fields",
            ['fields' => $fields],
        );
        $items = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        return array_map(
            static fn (array $f): DocumentFieldResponse => DocumentFieldResponse::from($f),
            $items,
        );
    }
}
