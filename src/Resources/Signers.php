<?php

declare(strict_types=1);

namespace Signori\Resources;

use Signori\Exceptions\NotFoundException;
use Signori\Responses\SignerResponse;
use Signori\Signori;

final class Signers
{
    /** Largest page the API accepts for ``GET /documents/{id}/signers``. */
    private const PAGE_SIZE = 100;

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
     * List every signer on a document, following ``next_cursor`` until the
     * last page.
     *
     * @return list<SignerResponse>
     */
    public function list(string $documentId): array
    {
        $signers = [];
        $cursor  = null;
        do {
            $data = $this->client->get("/api/v1/documents/{$documentId}/signers", [
                'limit'  => self::PAGE_SIZE,
                'cursor' => $cursor,
            ]);
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            foreach ($items as $item) {
                $signers[] = SignerResponse::from($item);
            }
            $next   = $data['next_cursor'] ?? null;
            $cursor = is_string($next) && $next !== '' && $next !== $cursor ? $next : null;
        } while ($cursor !== null);

        return $signers;
    }

    /**
     * Get a single signer on a document.
     *
     * The API has no single-signer route, so this lists the document's
     * signers and picks the matching id.
     *
     * @throws NotFoundException when the document has no signer with that id.
     */
    public function get(string $documentId, string $signerId): SignerResponse
    {
        foreach ($this->list($documentId) as $signer) {
            if ($signer->id === $signerId) {
                return $signer;
            }
        }

        throw new NotFoundException("Signer {$signerId} not found on document {$documentId}", 404);
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
