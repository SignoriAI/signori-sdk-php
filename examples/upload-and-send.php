<?php

/**
 * Example: Upload a PDF and send it for signing.
 *
 * Run:
 *   SIGNORI_API_KEY=your-key php examples/upload-and-send.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Signori\Exceptions\SignoriException;
use Signori\Signori;

// ── Client ───────────────────────────────────────────────────────────────────
// API key is read from SIGNORI_API_KEY env var.
// Pass it explicitly: Signori::client('sv_live_...')
$sv = Signori::client();

try {
    // ── 1. Upload ─────────────────────────────────────────────────────────────
    echo "Uploading document...\n";
    $doc = $sv->documents->upload(
        __DIR__ . '/fixtures/sample-contract.pdf',
        'Master Services Agreement – Acme Corp',
        'contract',
    );
    echo "  Created: {$doc->id} (status: {$doc->status})\n";

    // ── 2. Send for signing ───────────────────────────────────────────────────
    echo "Sending for signing...\n";
    $doc = $sv->documents->send(
        $doc->id,
        signers: [
            [
                'email'         => 'alice@acme-corp.example.com',
                'full_name'     => 'Alice Smith',
                'role'          => 'signer',
                'auth_method'   => 'email',
                'signing_order' => 1,
            ],
            [
                'email'         => 'legal@your-company.example.com',
                'full_name'     => 'Legal Team',
                'role'          => 'approver',
                'auth_method'   => 'email',
                'signing_order' => 2,
            ],
        ],
        message: 'Please review and sign the attached agreement.',
    );
    echo "  Status: {$doc->status}\n";
    echo "Done. Document ID: {$doc->id}\n";

} catch (SignoriException $e) {
    fprintf(STDERR, "Error [%s]: %s (request_id: %s)\n",
        get_class($e), $e->getMessage(), $e->requestId ?? 'n/a');
    exit(1);
}
