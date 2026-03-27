<?php

declare(strict_types=1);

namespace SignVault\Responses;

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
        return $r;
    }
}
