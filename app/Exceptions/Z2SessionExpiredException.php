<?php

namespace App\Exceptions;

/**
 * Exception for Z2 session expiry.
 */
class Z2SessionExpiredException extends Z2ApiException
{
    public function __construct(
        string $message = 'Z2 session has expired',
        ?int $statusCode = null,
        ?string $responseBody = null,
        string $endpoint = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $responseBody, $endpoint, $previous);
    }
}
