<?php

declare(strict_types=1);

namespace SignVault\Responses;

final class AuditTrailResponse extends BaseResponse
{
    public readonly string $documentId;
    public readonly bool   $chainValid;
    /** @var array<int, array<string, mixed>> */
    public readonly array  $events;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->documentId = $r->str('document_id');
        $r->chainValid = $r->bool('chain_valid');
        $r->events     = $r->arr('events');
        return $r;
    }
}
