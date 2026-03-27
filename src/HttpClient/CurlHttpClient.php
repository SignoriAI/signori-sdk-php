<?php

declare(strict_types=1);

namespace SignVault\HttpClient;

use SignVault\Exceptions\SignVaultException;

/**
 * Default HTTP transport using PHP's cURL extension.
 *
 * Zero external dependencies — ships with virtually every PHP install.
 * Supports JSON bodies, multipart file uploads, and raw binary responses.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly int $timeout = 30) {}

    /** {@inheritdoc} */
    public function send(
        string $method,
        string $url,
        array  $headers   = [],
        array  $json      = [],
        array  $multipart = [],
    ): array {
        [$status, $rawBody] = $this->execute($method, $url, $headers, $json, $multipart);

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            $decoded = ['raw' => $rawBody];
        }

        return [$status, $decoded];
    }

    /** {@inheritdoc} */
    public function sendRaw(string $method, string $url, array $headers = []): string
    {
        [, $body] = $this->execute($method, $url, $headers);
        return $body;
    }

    // -------------------------------------------------------------------------

    /** @return array{0: int, 1: string} */
    private function execute(
        string $method,
        string $url,
        array  $headers,
        array  $json      = [],
        array  $multipart = [],
    ): array {
        if (! extension_loaded('curl')) {
            throw new SignVaultException(
                'The cURL extension is required. Install it or provide a custom HttpClientInterface.'
            );
        }

        $ch = curl_init();

        // ── Headers ──────────────────────────────────────────────────────────
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        // ── Body ─────────────────────────────────────────────────────────────
        if ($multipart !== []) {
            $postFields = $this->buildMultipart($multipart);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            // Let cURL set the Content-Type with the correct boundary
        } elseif ($json !== []) {
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            $curlHeaders[] = 'Content-Type: application/json';
        }

        // ── Method ───────────────────────────────────────────────────────────
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // ── Options ──────────────────────────────────────────────────────────
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new SignVaultException("cURL error [{$errno}]: {$error}", 0, null, $url);
        }

        return [$status, (string) $body];
    }

    /**
     * Build multipart/form-data fields for file upload.
     *
     * @param array{file: string|resource|mixed, filename: string, extra: array} $multipart
     * @return array<string, mixed>  Passed directly to CURLOPT_POSTFIELDS
     */
    private function buildMultipart(array $multipart): array
    {
        $fields = [];

        $file     = $multipart['file'];
        $filename = $multipart['filename'] ?? 'document.pdf';
        $extra    = $multipart['extra'] ?? [];

        // Normalise to a CURLFile
        if (is_string($file) && is_file($file)) {
            $fields['file'] = new \CURLFile($file, 'application/pdf', $filename);
        } elseif (is_resource($file)) {
            // Read resource into memory, then write to a temp file for cURL
            $tmp = tmpfile();
            stream_copy_to_stream($file, $tmp);
            $meta = stream_get_meta_data($tmp);
            $fields['file'] = new \CURLFile($meta['uri'], 'application/pdf', $filename);
        } elseif (is_string($file)) {
            // Raw bytes — write to temp file
            $tmpPath = tempnam(sys_get_temp_dir(), 'sv_');
            file_put_contents($tmpPath, $file);
            $fields['file'] = new \CURLFile($tmpPath, 'application/pdf', $filename);
        }

        // Extra fields (title, document_type, etc.)
        foreach ($extra as $key => $value) {
            if ($value !== null) {
                $fields[$key] = (string) $value;
            }
        }

        return $fields;
    }
}
