<?php

namespace App\Exceptions;

use Exception;

/**
 * Custom exception for Z2 API errors.
 */
class Z2ApiException extends Exception
{
    /**
     * The HTTP status code from the Z2 API response.
     */
    public ?int $statusCode;

    /**
     * The raw response body from the Z2 API.
     */
    public ?string $responseBody;

    /**
     * The endpoint that was being called.
     */
    public string $endpoint;

    public function __construct(
        string $message = '',
        ?int $statusCode = null,
        ?string $responseBody = null,
        string $endpoint = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->endpoint = $endpoint;
    }

    /**
     * Get additional context for logging.
     */
    public function getContext(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'status_code' => $this->statusCode,
            'response_body' => $this->responseBody,
        ];
    }
}
