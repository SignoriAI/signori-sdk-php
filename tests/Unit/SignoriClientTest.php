<?php

declare(strict_types=1);

namespace Signori\Tests\Unit;

use Signori\Exceptions\AuthException;
use Signori\Exceptions\NotFoundException;
use Signori\Exceptions\RateLimitException;
use Signori\Exceptions\ValidationException;
use Signori\Exceptions\ApiException;
use Signori\Exceptions\SignoriException;
use Signori\Signori;
use Signori\Tests\MockHttpClient;
use Signori\Tests\UnitTestCase;

/**
 * Tests for the core client: env-based config, auth headers, error mapping,
 * retry logic, and URL construction.
 */
final class SignoriClientTest extends UnitTestCase
{
    // ── Construction ─────────────────────────────────────────────────────────

    public function test_throws_when_api_key_empty(): void
    {
        $this->expectException(SignoriException::class);
        Signori::client('');
    }

    public function test_reads_api_key_from_environment(): void
    {
        putenv('SIGNORI_API_KEY=env-key-xyz');
        $sv = Signori::client();
        $this->assertInstanceOf(Signori::class, $sv);
        putenv('SIGNORI_API_KEY'); // unset
    }

    public function test_reads_base_url_from_environment(): void
    {
        putenv('SIGNORI_API_KEY=env-key');
        putenv('SIGNORI_BASE_URL=https://custom.api');
        $http = new MockHttpClient();
        $http->enqueue(200, $this->envelope($this->documentFixture()));
        $sv = Signori::client()->withHttpClient($http);
        $sv->documents->get('doc_1');
        $this->assertStringStartsWith('https://custom.api', $http->lastRequest()['url']);
        putenv('SIGNORI_API_KEY');
        putenv('SIGNORI_BASE_URL');
    }

    // ── Authorization header ──────────────────────────────────────────────────

    public function test_sends_bearer_authorization_header(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));
        $this->sv->documents->get('doc_abc123');
        $headers = $this->http->lastRequest()['headers'];
        $this->assertSame('Bearer test-key-unit', $headers['Authorization']);
    }

    public function test_sends_correct_user_agent(): void
    {
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));
        $this->sv->documents->get('doc_abc123');
        $headers = $this->http->lastRequest()['headers'];
        $this->assertStringStartsWith('signori-php/', $headers['User-Agent']);
    }

    // ── URL construction ──────────────────────────────────────────────────────

    public function test_builds_url_with_query_string(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([]));
        $this->sv->documents->list(['status' => 'completed', 'limit' => 10]);
        $url = $this->http->lastRequest()['url'];
        $this->assertStringContainsString('status=completed', $url);
        $this->assertStringContainsString('limit=10', $url);
    }

    public function test_strips_null_query_params(): void
    {
        $this->http->enqueue(200, $this->paginatedEnvelope([]));
        $this->sv->documents->list(['status' => null, 'limit' => 10]);
        $url = $this->http->lastRequest()['url'];
        $this->assertStringNotContainsString('status', $url);
    }

    public function test_trailing_slash_stripped_from_base_url(): void
    {
        $http = new MockHttpClient();
        $http->enqueue(200, $this->envelope($this->documentFixture()));
        $sv = Signori::client('key', 'https://api.test/')->withHttpClient($http);
        $sv->documents->get('d1');
        $url = $http->lastRequest()['url'];
        $this->assertStringNotContainsString('//', str_replace('https://', '', $url));
    }

    // ── Error mapping ─────────────────────────────────────────────────────────

    public function test_401_throws_auth_exception(): void
    {
        $this->http->enqueue(401, ['error' => ['code' => 'UNAUTHORIZED', 'message' => 'Invalid key']]);
        $this->expectException(AuthException::class);
        $this->sv->documents->get('x');
    }

    public function test_403_throws_auth_exception(): void
    {
        $this->http->enqueue(403, ['error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied']]);
        $this->expectException(AuthException::class);
        $this->sv->documents->get('x');
    }

    public function test_404_throws_not_found_exception(): void
    {
        $this->http->enqueue(404, ['error' => ['code' => 'NOT_FOUND', 'message' => 'Not found']]);
        $this->expectException(NotFoundException::class);
        $this->sv->documents->get('x');
    }

    public function test_422_throws_validation_exception(): void
    {
        $this->http->enqueue(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Bad input']]);
        $this->expectException(ValidationException::class);
        $this->sv->documents->get('x');
    }

    public function test_400_throws_validation_exception(): void
    {
        $this->http->enqueue(400, ['error' => ['code' => 'BAD_REQUEST', 'message' => 'Bad request']]);
        $this->expectException(ValidationException::class);
        $this->sv->documents->get('x');
    }

    public function test_429_throws_rate_limit_exception(): void
    {
        // enqueue two: first is the 429, second is the retry (also fails to not loop)
        $this->http->enqueue(429, ['error' => ['code' => 'RATE_LIMITED', 'message' => 'Slow down']]);
        $this->http->enqueue(429, ['error' => ['code' => 'RATE_LIMITED', 'message' => 'Slow down']]);
        $this->expectException(RateLimitException::class);
        $sv = Signori::client('key', 'https://api.test', maxRetries: 0)
            ->withHttpClient($this->http);
        $sv->documents->get('x');
    }

    public function test_500_throws_api_exception(): void
    {
        $this->http->enqueue(500, ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Boom']]);
        $this->http->enqueue(500, ['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Boom']]);
        $this->expectException(ApiException::class);
        $sv = Signori::client('key', 'https://api.test', maxRetries: 0)
            ->withHttpClient($this->http);
        $sv->documents->get('x');
    }

    public function test_exception_carries_request_id(): void
    {
        $this->http->enqueue(404, [
            'error'      => ['code' => 'NOT_FOUND', 'message' => 'Not found'],
            'request_id' => 'req_abc_xyz',
        ]);
        try {
            $this->sv->documents->get('x');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('req_abc_xyz', $e->requestId);
        }
    }

    // ── Retry logic ───────────────────────────────────────────────────────────

    public function test_retries_once_on_500_then_succeeds(): void
    {
        $this->http->enqueue(500, ['error' => ['code' => 'SERVER_ERROR', 'message' => 'tmp']]);
        $this->http->enqueue(200, $this->envelope($this->documentFixture()));

        // maxRetries=1 is the default
        $doc = $this->sv->documents->get('doc_abc123');
        $this->assertSame(2, $this->http->requestCount());
        $this->assertSame('doc_abc123', $doc->id);
    }
}
