<?php

namespace App\Exceptions;

/**
 * Exception for Z2 authentication failures.
 */
class Z2AuthenticationException extends Z2ApiException
{
    public function __construct(
        string $message = 'Z2 authentication failed',
        ?int $statusCode = null,
        ?string $responseBody = null,
        string $endpoint = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $responseBody, $endpoint, $previous);
    }
}
