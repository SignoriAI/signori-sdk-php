<?php

/**
 * Example: Receive and verify a Signori webhook.
 *
 * Drop this file into your web root (e.g. public/webhook.php) and point
 * your Signori webhook URL at it.
 *
 * Environment variables expected:
 *   SIGNORI_WEBHOOK_SECRET  — the signing secret shown in the dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Signori\Exceptions\SignoriException;
use Signori\Resources\Webhooks;

// ── 1. Read raw body BEFORE any framework decodes it ─────────────────────────
$payload   = (string) file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNORI_SIGNATURE'] ?? '';
$secret    = (string) getenv('SIGNORI_WEBHOOK_SECRET');

// ── 2. Verify signature ───────────────────────────────────────────────────────
if (! Webhooks::verify($payload, $signature, $secret)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// ── 3. Parse the event ────────────────────────────────────────────────────────
try {
    $event = Webhooks::constructEvent($payload);
} catch (SignoriException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// ── 4. Handle event types ─────────────────────────────────────────────────────
$documentId = $event['data']['id'] ?? null;

switch ($event['event']) {
    case 'document.completed':
        // All signers have signed — download and store the signed PDF
        error_log("Document completed: {$documentId}");
        // $sv->documents->downloadSigned($documentId) ...
        break;

    case 'signer.signed':
        $signerEmail = $event['data']['email'] ?? 'unknown';
        error_log("Signer signed: {$signerEmail} on document {$documentId}");
        break;

    case 'document.declined':
        error_log("Document declined: {$documentId}");
        break;

    case 'document.voided':
        error_log("Document voided: {$documentId}");
        break;

    default:
        // Unknown event type — log and ignore
        error_log("Unknown Signori event: {$event['event']}");
}

// ── 5. Acknowledge ────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['received' => true]);
