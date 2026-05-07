<?php

declare(strict_types=1);

namespace Signori\Resources;

use Signori\Responses\SignerResponse;
use Signori\Signori;

final class Signers
{
    public function __construct(private readonly Signori $client) {}

    /**
     * Add a signer to an existing document.
     *
     * @param  string  $role        signer | approver | viewer
     * @param  string  $authMethod  email | sms_otp | kba | id_scan
     */
    public function add(
        string  $documentId,
        string  $email,
        string  $fullName,
        string  $role       = 'signer',
        string  $authMethod = 'email',
        ?string $phone      = null,
        int     $signingOrder = 1,
    ): SignerResponse {
        $data = $this->client->post("/api/v1/documents/{$documentId}/signers", array_filter([
            'email'         => $email,
            'full_name'     => $fullName,
            'role'          => $role,
            'auth_method'   => $authMethod,
            'phone'         => $phone,
            'signing_order' => $signingOrder,
        ], fn($v) => $v !== null));

        return SignerResponse::from($data);
    }

    /**
     * Get a single signer by ID.
     */
    public function get(string $documentId, string $signerId): SignerResponse
    {
        $data = $this->client->get("/api/v1/documents/{$documentId}/signers/{$signerId}");
        return SignerResponse::from($data);
    }

    /**
     * Send a reminder email to a pending signer.
     */
    public function remind(string $documentId, string $signerId): SignerResponse
    {
        $data = $this->client->post("/api/v1/documents/{$documentId}/signers/{$signerId}/remind");
        return SignerResponse::from($data);
    }

    /**
     * Remove a signer from a document (only valid when document is still a draft).
     */
    public function remove(string $documentId, string $signerId): array
    {
        return $this->client->delete("/api/v1/documents/{$documentId}/signers/{$signerId}");
    }
}
