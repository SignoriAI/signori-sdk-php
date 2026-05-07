<?php

declare(strict_types=1);

namespace Signori\Responses;

final class SignerResponse extends BaseResponse
{
    public readonly string  $id;
    public readonly string  $documentId;
    public readonly string  $email;
    public readonly string  $fullName;
    public readonly ?string $phone;
    public readonly string  $role;
    public readonly string  $status;
    public readonly int     $signingOrder;
    public readonly string  $authMethod;
    public readonly ?string $signedAt;
    public readonly ?string $declinedAt;
    public readonly string  $createdAt;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id           = $r->str('id');
        $r->documentId   = $r->str('document_id');
        $r->email        = $r->str('email');
        $r->fullName     = $r->str('full_name');
        $r->phone        = $r->nullable('phone');
        $r->role         = $r->str('role');
        $r->status       = $r->str('status');
        $r->signingOrder = $r->int('signing_order');
        $r->authMethod   = $r->str('auth_method');
        $r->signedAt     = $r->nullable('signed_at');
        $r->declinedAt   = $r->nullable('declined_at');
        $r->createdAt    = $r->str('created_at');
        return $r;
    }
}
