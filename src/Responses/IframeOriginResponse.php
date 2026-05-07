<?php

declare(strict_types=1);

namespace Signori\Responses;

final class IframeOriginResponse extends BaseResponse
{
    public readonly string $id;
    public readonly string $origin;
    public readonly bool   $isActive;
    public readonly string $createdBy;
    public readonly string $createdAt;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id        = $r->str('id');
        $r->origin    = $r->str('origin');
        $r->isActive  = $r->bool('is_active');
        $r->createdBy = $r->str('created_by');
        $r->createdAt = $r->str('created_at');
        return $r;
    }
}
