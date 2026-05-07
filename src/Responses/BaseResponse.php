<?php

declare(strict_types=1);

namespace Signori\Responses;

/**
 * Immutable response object. All properties are readonly.
 * Call toArray() to get the raw data back.
 */
abstract class BaseResponse
{
    protected array $raw;

    protected function __construct(array $data)
    {
        $this->raw = $data;
    }

    /** Return the underlying raw array from the API. */
    public function toArray(): array
    {
        return $this->raw;
    }

    protected function str(string $key, string $default = ''): string
    {
        return isset($this->raw[$key]) ? (string) $this->raw[$key] : $default;
    }

    protected function int(string $key, int $default = 0): int
    {
        return isset($this->raw[$key]) ? (int) $this->raw[$key] : $default;
    }

    protected function bool(string $key, bool $default = false): bool
    {
        return isset($this->raw[$key]) ? (bool) $this->raw[$key] : $default;
    }

    protected function nullable(string $key): ?string
    {
        return isset($this->raw[$key]) && $this->raw[$key] !== null
            ? (string) $this->raw[$key]
            : null;
    }

    protected function arr(string $key): array
    {
        return is_array($this->raw[$key] ?? null) ? $this->raw[$key] : [];
    }
}
