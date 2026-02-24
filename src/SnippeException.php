<?php

namespace Snippe;

class SnippeException extends \Exception
{
    private array $response;

    public function __construct(string $message, int $code = 0, array $response = [], ?\Throwable $previous = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the full API error response
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Get the API error code string (e.g. "validation_error", "unauthorized")
     */
    public function getErrorCode(): ?string
    {
        return $this->response['error_code'] ?? null;
    }
}
