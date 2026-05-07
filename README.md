# Signori PHP SDK

Official PHP SDK for the [Signori](https://signori.ai) e-signature API.

**Requirements:** PHP 8.1+ · cURL extension (standard on all hosts)  
**Dependencies:** PSR interfaces only — no Guzzle, no Symfony, no framework lock-in

---

## Installation

```bash
composer require signori/signori-php
```

The SDK ships with its own cURL-based HTTP client, so no extra packages are needed. If you already use Guzzle or Symfony HttpClient you can [swap in your own transport](#custom-http-client).

---

## Quick start

```php
use Signori\Signori;

$sv = Signori::client('sv_live_your_api_key');

// Upload a PDF
$doc = $sv->documents->upload('/path/to/contract.pdf', 'Master Services Agreement');

// Add signers and send
$sv->documents->send($doc->id, [
    ['email' => 'alice@example.com', 'full_name' => 'Alice Smith'],
]);

echo "Sent! Document ID: {$doc->id}\n";
```

---

## Configuration

### Via environment variables (recommended)

```bash
# .env (copy from .env.example)
SIGNORI_API_KEY=sv_live_your_api_key
SIGNORI_BASE_URL=https://api.signori.ai   # optional, this is the default
```

```php
$sv = Signori::client(); // reads SIGNORI_API_KEY automatically
```

### Explicitly

```php
$sv = Signori::client(
    apiKey:     'sv_live_your_api_key',
    baseUrl:    'https://api.signori.ai', // or http://localhost:8000 for local dev
    timeout:    30,                          // seconds
    maxRetries: 1,                           // auto-retry on 429 / 5xx
);
```

---

## Documents

### Upload

```php
// From a file path
$doc = $sv->documents->upload('/path/to/file.pdf', 'NDA', 'nda');

// From an open file handle
$fh  = fopen('/path/to/file.pdf', 'rb');
$doc = $sv->documents->upload($fh, 'NDA');

// From raw bytes (e.g. generated PDF)
$doc = $sv->documents->upload($pdfBytes, 'Generated Agreement');
```

### List

```php
$page = $sv->documents->list([
    'status'        => 'completed',   // draft | pending | in_progress | completed | voided
    'document_type' => 'contract',
    'search'        => 'Acme',
    'limit'         => 20,
]);

foreach ($page->items as $doc) {
    echo "{$doc->id}  {$doc->title}  {$doc->status}\n";
}

// Paginate
if ($page->hasMore()) {
    $next = $sv->documents->list(['cursor' => $page->nextCursor]);
}
```

### Get

```php
$doc = $sv->documents->get('doc_abc123');
echo $doc->status;      // draft | pending | in_progress | completed | voided
echo $doc->pageCount;
echo $doc->createdAt;
```

### Send for signing

```php
$doc = $sv->documents->send(
    $doc->id,
    signers: [
        [
            'email'         => 'alice@example.com',
            'full_name'     => 'Alice Smith',
            'role'          => 'signer',      // signer | approver | viewer
            'auth_method'   => 'email',        // email | sms_otp | kba | id_scan
            'signing_order' => 1,
        ],
    ],
    message:       'Please review and sign.',
    expiryDays:    14,
    sendReminders: true,
);
```

### Void

```php
$sv->documents->void($doc->id, 'Sent to wrong recipient');
```

### Download

```php
// Download the original (unsigned) PDF
$bytes = $sv->documents->downloadOriginal($doc->id);
file_put_contents('/tmp/original.pdf', $bytes);

// Download the signed PDF (available after all signers complete)
$bytes = $sv->documents->downloadSigned($doc->id);
file_put_contents('/tmp/signed.pdf', $bytes);
```

### Audit trail

```php
$trail = $sv->documents->auditTrail($doc->id);
echo $trail->chainValid ? "Chain intact\n" : "WARNING: chain broken\n";

foreach ($trail->events as $event) {
    echo "{$event['occurred_at']}  {$event['event_type']}  {$event['actor_email']}\n";
}
```

---

## Signers

```php
// Add a signer
$signer = $sv->signers->add(
    documentId:   $doc->id,
    email:        'alice@example.com',
    fullName:     'Alice Smith',
    role:         'signer',
    authMethod:   'sms_otp',
    phone:        '+14155550100',
    signingOrder: 1,
);

// Send reminder
$sv->signers->remind($doc->id, $signer->id);

// Remove (draft documents only)
$sv->signers->remove($doc->id, $signer->id);
```

---

## Templates

```php
// List templates
$page = $sv->templates->list();

// Get a specific template
$tpl = $sv->templates->get('tpl_abc123');
echo $tpl->name;
foreach ($tpl->fields as $field) {
    echo "  {$field['name']} ({$field['type']})\n";
}

// Create a document from a template
$doc = $sv->templates->createDocument(
    'tpl_abc123',
    fieldValues: [
        'party_name'     => 'Acme Corp',
        'effective_date' => '2026-06-01',
    ],
    signers: [
        ['email' => 'ceo@acme.com', 'full_name' => 'Jane CEO'],
    ],
);
```

---

## Webhooks

### Register an endpoint

```php
$wh = $sv->webhooks->create(
    'https://yourapp.com/webhooks/signori',
    events: [
        'document.completed',
        'document.declined',
        'document.voided',
        'signer.signed',
        'signer.declined',
    ],
);
echo "Webhook ID: {$wh->id}\n";
```

### Verify incoming webhooks

```php
// webhook-handler.php
use Signori\Resources\Webhooks;

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNORI_SIGNATURE'] ?? '';
$secret    = getenv('SIGNORI_WEBHOOK_SECRET');

if (! Webhooks::verify($payload, $signature, $secret)) {
    http_response_code(403);
    exit;
}

$event = Webhooks::constructEvent($payload);

switch ($event['event']) {
    case 'document.completed':
        $docId = $event['data']['id'];
        // download signed PDF, update your DB, notify users ...
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
```

See [`examples/webhook-handler.php`](examples/webhook-handler.php) for a complete example.

---

## Error handling

```php
use Signori\Exceptions\AuthException;
use Signori\Exceptions\NotFoundException;
use Signori\Exceptions\ValidationException;
use Signori\Exceptions\RateLimitException;
use Signori\Exceptions\ApiException;
use Signori\Exceptions\SignoriException;

try {
    $doc = $sv->documents->get('doc_xyz');
} catch (NotFoundException $e) {
    echo "Not found. Request ID: {$e->requestId}\n";
} catch (AuthException $e) {
    echo "Auth failed — check your API key.\n";
} catch (ValidationException $e) {
    echo "Bad request: {$e->getMessage()}\n";
} catch (RateLimitException $e) {
    echo "Rate limited — slow down.\n";
} catch (ApiException $e) {
    echo "Server error: {$e->getMessage()}\n";
} catch (SignoriException $e) {
    // Catches any of the above + network/config errors
    echo "SDK error: {$e->getMessage()}\n";
}
```

All exceptions expose:
- `getMessage()` — human-readable description
- `getCode()` — HTTP status code
- `$e->requestId` — Signori request ID (include in support tickets)

---

## Custom HTTP client

Swap in Guzzle (or any PSR-18 client) if you already have one:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Signori\HttpClient\PsrHttpClient;

$factory   = new HttpFactory();
$transport = new PsrHttpClient(new Client(), $factory, $factory);
$sv        = Signori::client()->withHttpClient($transport);
```

Or inject a mock in tests:

```php
use Signori\Tests\MockHttpClient;

$mock = new MockHttpClient();
$mock->enqueue(200, ['data' => ['id' => 'doc_1', 'title' => 'Test', ...]]);
$sv   = Signori::client('test-key')->withHttpClient($mock);
```

---

## Running tests

```bash
# Install dependencies
composer install

# Unit tests (no network, no API key needed)
composer test:unit

# Integration tests (hits real API)
SIGNORI_API_KEY=sv_live_... composer test:integration

# All tests
composer test
```

Integration tests are automatically skipped when `SIGNORI_API_KEY` is not set, so `composer test` is always safe to run in CI without credentials.

---

## Response objects

All API responses are typed readonly DTOs. Every DTO exposes:

- Named properties with IDE autocomplete (`$doc->id`, `$doc->status`, etc.)
- `toArray()` — returns the original raw API response for debugging

```php
$doc = $sv->documents->get('doc_abc123');

// Typed access
echo $doc->id;           // string
echo $doc->title;        // string
echo $doc->status;       // string: draft | pending | in_progress | completed | voided
echo $doc->pageCount;    // int
echo $doc->createdAt;    // string (ISO 8601)
var_dump($doc->completedAt); // ?string

// Raw access
print_r($doc->toArray());
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## License

MIT. See [LICENSE](LICENSE).
