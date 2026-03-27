<?php

declare(strict_types=1);

namespace SignVault\Responses;

final class ApiKeyResponse extends BaseResponse
{
    public readonly string  $id;
    public readonly string  $name;
    public readonly ?string $key;        // Only present on creation (full_key); null afterwards
    public readonly string  $prefix;     // e.g. "sv_live_e6f3"
    public readonly string  $createdAt;
    public readonly ?string $lastUsedAt;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id          = $r->str('id');
        $r->name        = $r->str('name');
        $r->key         = $r->nullable('full_key');
        $r->prefix      = $r->str('key_prefix');
        $r->createdAt   = $r->str('created_at');
        $r->lastUsedAt  = $r->nullable('last_used_at');
        return $r;
    }
}
