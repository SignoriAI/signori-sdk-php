<?php

declare(strict_types=1);

namespace SignVault\Responses;

final class TemplateResponse extends BaseResponse
{
    public readonly string  $id;
    public readonly string  $name;
    public readonly ?string $description;
    public readonly string  $documentType;
    public readonly bool    $isSystem;
    public readonly array   $fields;
    public readonly string  $createdAt;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id           = $r->str('id');
        $r->name         = $r->str('name');
        $r->description  = $r->nullable('description');
        $r->documentType = $r->str('document_type');
        $r->isSystem     = $r->bool('is_system');
        $r->fields       = $r->arr('fields');
        $r->createdAt    = $r->str('created_at');
        return $r;
    }
}
