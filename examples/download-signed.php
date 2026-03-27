<?php

/**
 * Example: Download the signed PDF once a document is completed.
 *
 * Run:
 *   SIGNVAULT_API_KEY=your-key php examples/download-signed.php doc_your_id_here
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SignVault\Exceptions\SignVaultException;
use SignVault\SignVault;

$docId = $argv[1] ?? null;
if ($docId === null) {
    echo "Usage: php examples/download-signed.php <document_id>\n";
    exit(1);
}

$sv = SignVault::client();

try {
    // ── Check status first ────────────────────────────────────────────────────
    $doc = $sv->documents->get($docId);
    echo "Document: {$doc->title} (status: {$doc->status})\n";

    if ($doc->status !== 'completed') {
        echo "Document is not yet completed. Current status: {$doc->status}\n";
        exit(0);
    }

    // ── Download signed PDF ───────────────────────────────────────────────────
    $bytes    = $sv->documents->downloadSigned($docId);
    $filename = preg_replace('/[^a-z0-9_-]/i', '_', $doc->title) . '_signed.pdf';
    $path     = sys_get_temp_dir() . '/' . $filename;

    file_put_contents($path, $bytes);
    echo "Saved: {$path} (" . strlen($bytes) . " bytes)\n";

    // ── Print audit trail ─────────────────────────────────────────────────────
    $trail = $sv->documents->auditTrail($docId);
    echo "\nAudit trail (chain valid: " . ($trail->chainValid ? 'yes' : 'NO') . "):\n";
    foreach ($trail->events as $entry) {
        $ts    = $entry['occurred_at'] ?? 'unknown time';
        $type  = $entry['event_type']  ?? 'unknown';
        $actor = $entry['actor_email'] ?? $entry['actor_ip'] ?? 'system';
        printf("  %s  %-30s  %s\n", $ts, $type, $actor);
    }

} catch (SignVaultException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
