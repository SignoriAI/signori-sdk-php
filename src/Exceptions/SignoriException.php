<?php

declare(strict_types=1);

namespace Signori\Exceptions;

use RuntimeException;

/**
 * Base exception for all Signori SDK errors.
 */
class SignoriException extends RuntimeException
{
    public function __construct(
        string               $message  = '',
        int                  $code     = 0,
        ?\Throwable          $previous = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Factory: map API error codes to the most specific exception subclass.
     */
    public static function fromApiError(
        int     $httpStatus,
        string  $code,
        string  $message,
        ?string $requestId = null,
    ): self {
        return match (true) {
            $httpStatus === 401                        => new AuthException($message, $httpStatus, null, $requestId),
            $httpStatus === 403                        => new AuthException($message, $httpStatus, null, $requestId),
            $httpStatus === 404                        => new NotFoundException($message, $httpStatus, null, $requestId),
            $httpStatus === 429                        => new RateLimitException($message, $httpStatus, null, $requestId),
            in_array($httpStatus, [400, 422], true)   => new ValidationException($message, $httpStatus, null, $requestId),
            default                                    => new ApiException($message, $httpStatus, null, $requestId),
        };
    }
}
