<?php

namespace Snippe;

/**
 * Handles and verifies incoming Snippe webhook events.
 *
 * Signatures use HMAC-SHA256 over the exact raw request body and are sent as:
 *
 *   X-Snippe-Signature: sha256=<hex digest>
 */
class Webhook
{
    public const SIGNATURE_HEADER = 'x-snippe-signature';

    private array $payload;
    private array $headers;
    private string $eventType;
    private string $rawBody;

    private function __construct(array $payload, array $headers, string $rawBody)
    {
        $this->payload = $payload;
        $this->rawBody = $rawBody;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->eventType = $this->headers['x-webhook-event'] ?? '';
    }

    /**
     * Capture and verify the current incoming webhook request.
     *
     * The secret must come from secure server-side configuration. Never expose it
     * in client-side code or commit it to source control.
     *
     * @throws SnippeException if verification fails or the payload is invalid
     */
    public static function capture(string $secret): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : self::parseHeaders();
        $rawBody = file_get_contents('php://input');

        if ($rawBody === false) {
            http_response_code(400);
            throw new SnippeException('Unable to read webhook payload', 400);
        }

        try {
            return self::fromRaw($rawBody, $headers, $secret);
        } catch (SnippeException $exception) {
            http_response_code($exception->getCode());
            throw $exception;
        }
    }

    /**
     * Create and verify a webhook from raw data. Useful for framework adapters
     * and automated tests.
     *
     * @throws SnippeException if verification fails or the payload is invalid
     */
    public static function fromRaw(string $body, array $headers, string $secret): self
    {
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        self::verifySignature($body, $normalizedHeaders, $secret);

        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            $message = json_last_error() === JSON_ERROR_NONE
                ? 'Webhook payload must be a JSON object or array'
                : 'Invalid webhook payload: ' . json_last_error_msg();

            throw new SnippeException($message, 400);
        }

        return new self($payload, $normalizedHeaders, $body);
    }

    /**
     * Verify an HMAC-SHA256 signature against the exact raw request body.
     *
     * @throws SnippeException when the secret/signature is missing or invalid
     */
    private static function verifySignature(string $body, array $headers, string $secret): void
    {
        if ($secret === '') {
            throw new SnippeException('Webhook secret must not be empty', 500);
        }

        $provided = $headers[self::SIGNATURE_HEADER] ?? null;

        if (!is_string($provided) || $provided === '') {
            throw new SnippeException('Missing webhook signature', 401);
        }

        if (!preg_match('/^sha256=[a-f0-9]{64}$/i', $provided)) {
            throw new SnippeException('Invalid webhook signature format', 401);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        if (!hash_equals($expected, strtolower($provided))) {
            throw new SnippeException('Invalid webhook signature', 401);
        }
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function isPaymentCompleted(): bool
    {
        return $this->eventType === 'payment.completed' && $this->status() === 'completed';
    }

    public function isPaymentFailed(): bool
    {
        return $this->eventType === 'payment.failed' && $this->status() === 'failed';
    }

    public function reference(): ?string
    {
        $reference = $this->data()['reference'] ?? $this->payload['reference'] ?? null;

        return is_string($reference) ? $reference : null;
    }

    public function status(): ?string
    {
        $status = $this->data()['status'] ?? $this->payload['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function amount(): ?int
    {
        $amount = $this->data()['amount'] ?? null;

        if (is_array($amount)) {
            $amount = $amount['value'] ?? null;
        }

        return is_int($amount) ? $amount : null;
    }

    public function currency(): ?string
    {
        $amount = $this->data()['amount'] ?? null;
        $currency = is_array($amount) ? ($amount['currency'] ?? null) : null;

        return is_string($currency) ? $currency : null;
    }

    public function customer(): array
    {
        $customer = $this->data()['customer'] ?? [];

        return is_array($customer) ? $customer : [];
    }

    public function metadata(): array
    {
        $metadata = $this->data()['metadata'] ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function ok(): void
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function fail(int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false]);
    }

    private function data(): array
    {
        $data = $this->payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Fallback header parser for environments without getallheaders().
     */
    private static function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
