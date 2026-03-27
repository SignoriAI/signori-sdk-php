<?php declare(strict_types=1);
namespace SignVault\Exceptions;
/** Thrown on 429 — rate limit exceeded. Retry after the suggested delay. */
class RateLimitException extends SignVaultException {}
