<?php

/**
 * Example: Create a document from a saved template, pre-fill fields, send.
 *
 * Run:
 *   SIGNVAULT_API_KEY=your-key php examples/from-template.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SignVault\Exceptions\SignVaultException;
use SignVault\SignVault;

$sv = SignVault::client();

try {
    // ── 1. Find the template by listing and filtering by name ─────────────────
    $templates = $sv->templates->list();
    $template  = null;
    foreach ($templates->items as $tpl) {
        if (str_contains($tpl->name, 'NDA')) {
            $template = $tpl;
            break;
        }
    }

    if ($template === null) {
        echo "No NDA template found. Create one in the SignVault dashboard first.\n";
        exit(0);
    }

    echo "Using template: {$template->name} ({$template->id})\n";

    // ── 2. Create a document from the template with pre-filled values ─────────
    $doc = $sv->templates->createDocument(
        $template->id,
        fieldValues: [
            'party_name'     => 'Acme Corp',
            'effective_date' => date('Y-m-d'),
            'term_years'     => '2',
        ],
        signers: [
            ['email' => 'ceo@acme.example.com', 'full_name' => 'Jane CEO', 'role' => 'signer'],
        ],
    );

    echo "Document created: {$doc->id} (status: {$doc->status})\n";

    // ── 3. Send it ────────────────────────────────────────────────────────────
    $doc = $sv->documents->send(
        $doc->id,
        signers: [
            ['email' => 'ceo@acme.example.com', 'full_name' => 'Jane CEO'],
        ],
    );
    echo "Sent. Status: {$doc->status}\n";

} catch (SignVaultException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
