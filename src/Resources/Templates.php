<?php

declare(strict_types=1);

namespace SignVault\Resources;

use SignVault\Responses\DocumentResponse;
use SignVault\Responses\PaginatedResponse;
use SignVault\Responses\TemplateResponse;
use SignVault\SignVault;

final class Templates
{
    public function __construct(private readonly SignVault $client) {}

    /**
     * List all templates (system + company).
     */
    public function list(int $limit = 50, ?string $cursor = null): PaginatedResponse
    {
        $data = $this->client->get('/api/v1/templates', array_filter([
            'limit'  => $limit,
            'cursor' => $cursor,
        ], fn($v) => $v !== null));

        return PaginatedResponse::from($data, fn(array $t) => TemplateResponse::from($t));
    }

    /**
     * Retrieve a single template by ID.
     */
    public function get(string $id): TemplateResponse
    {
        $data = $this->client->get("/api/v1/templates/{$id}");
        return TemplateResponse::from($data);
    }

    /**
     * Create a document from a template, pre-filling fields.
     *
     * @param  array<string, string> $fieldValues  Field name → value map
     * @param  array<int, array{email: string, full_name: string, role?: string}> $signers
     */
    public function createDocument(
        string $templateId,
        array  $fieldValues = [],
        array  $signers     = [],
    ): DocumentResponse {
        $data = $this->client->post("/api/v1/templates/{$templateId}/documents", [
            'field_values' => $fieldValues,
            'signers'      => $signers,
        ]);

        return DocumentResponse::from($data);
    }

    /**
     * Pre-fill field values from a contact record.
     *
     * @return array<string, string>
     */
    public function prefillFromContact(string $templateId, string $contactId): array
    {
        return $this->client->post("/api/v1/templates/{$templateId}/prefill", [
            'contact_id' => $contactId,
        ]);
    }
}
