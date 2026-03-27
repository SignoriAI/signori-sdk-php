<?php

declare(strict_types=1);

namespace SignVault\Responses;

final class WebhookResponse extends BaseResponse
{
    public readonly string $id;
    public readonly string $url;
    public readonly array  $events;
    public readonly bool   $isActive;
    public readonly string $createdAt;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id        = $r->str('id');
        $r->url       = $r->str('url');
        $r->events    = $r->arr('events');
        $r->isActive  = $r->bool('is_active');
        $r->createdAt = $r->str('created_at');
        return $r;
    }
}
