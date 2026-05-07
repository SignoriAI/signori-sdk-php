<?php

declare(strict_types=1);

namespace Signori\Responses;

final class DocumentResponse extends BaseResponse
{
    public readonly string  $id;
    public readonly string  $title;
    public readonly string  $status;
    public readonly string  $documentType;
    public readonly ?string $description;
    public readonly int     $pageCount;
    public readonly ?string $templateId;
    public readonly string  $createdAt;
    public readonly ?string $completedAt;
    public readonly ?string $expiresAt;

    /**
     * Per-signer signing URLs returned by ``documents->send()``.
     *
     * Empty for endpoints that don't surface signing info (``get``, ``list``).
     * Each entry: ``['signer_id' => string, 'email' => string, 'signing_url' => string]``.
     *
     * @var array<int, array{signer_id: string, email: string, signing_url: string}>
     */
    public readonly array $signers;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id           = $r->str('id');
        $r->title        = $r->str('title');
        $r->status       = $r->str('status');
        $r->documentType = $r->str('document_type');
        $r->description  = $r->nullable('description');
        $r->pageCount    = $r->int('page_count');
        $r->templateId   = $r->nullable('template_id');
        $r->createdAt    = $r->str('created_at');
        $r->completedAt  = $r->nullable('completed_at');
        $r->expiresAt    = $r->nullable('expires_at');
        $r->signers      = self::normalizeSigners($r->arr('signers'));
        return $r;
    }

    /**
     * @param  array<mixed>  $raw
     * @return array<int, array{signer_id: string, email: string, signing_url: string}>
     */
    private static function normalizeSigners(array $raw): array
    {
        $out = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $out[] = [
                'signer_id'   => (string) ($entry['signer_id'] ?? ''),
                'email'       => (string) ($entry['email'] ?? ''),
                'signing_url' => (string) ($entry['signing_url'] ?? ''),
            ];
        }
        return $out;
    }
}
