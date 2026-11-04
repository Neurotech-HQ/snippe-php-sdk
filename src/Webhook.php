<?php

namespace Snippe;

/**
 * Handles and verifies incoming Snippe webhook events.
 *
 * Signature is HMAC-SHA256 of "{timestamp}.{raw body}", sent as
 * X-Webhook-Signature. X-Webhook-Timestamp is the unix time.
 */
class Webhook
{
    public const SIGNATURE_HEADER = 'x-webhook-signature';
    public const TIMESTAMP_HEADER = 'x-webhook-timestamp';
    public const TOLERANCE_SECONDS = 300;

    private array $payload;
    private array $headers;
    private string $eventType;
    private string $rawBody;

    private function __construct(array $payload, array $headers, string $rawBody)
    {
        $this->payload = $payload;
        $this->rawBody = $rawBody;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $eventType = $this->headers['x-webhook-event'] ?? '';
        if ($eventType === '' && isset($payload['type']) && is_string($payload['type'])) {
            $eventType = $payload['type'];
        }
        $this->eventType = $eventType;
    }

    // ── Signature Verification ──

    /**
     * Verify the webhook signature using HMAC-SHA256.
     *
     * @param string $secret Your webhook secret from the Snippe dashboard
     * @return bool True if the signature is valid
     */
    public function isValid(string $secret): bool
    {
        try {
            self::verifySignature($this->rawBody, $this->headers, $secret);
            return true;
        } catch (SnippeException) {
            return false;
        }
    }

    /**
     * Verify signature or throw an exception.
     *
     * @param string $secret Your webhook secret
     * @throws SnippeException if the signature is invalid
     */
    public function verify(string $secret): self
    {
        if (!$this->isValid($secret)) {
            throw new SnippeException('Invalid webhook signature', 401);
        }

        return $this;
    }

    // ── Factory methods ──

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
     * @param array<string, mixed> $headers Lowercase header names
     * @throws SnippeException
     */
    private static function verifySignature(string $body, array $headers, string $secret): void
    {
        if ($secret === '') {
            throw new SnippeException('Webhook secret must not be empty', 500);
        }

        $provided = $headers[self::SIGNATURE_HEADER] ?? null;
        $timestamp = $headers[self::TIMESTAMP_HEADER] ?? null;

        if (!is_string($provided) || $provided === '' || !is_string($timestamp) || $timestamp === '') {
            throw new SnippeException('Missing webhook signature', 401);
        }

        if (!preg_match('/^\d+$/', $timestamp)) {
            throw new SnippeException('Invalid webhook timestamp', 401);
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            throw new SnippeException('Webhook timestamp expired', 401);
        }

        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $provided)) {
            throw new SnippeException('Invalid webhook signature format', 401);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

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

    public function isPayoutCompleted(): bool
    {
        return $this->eventType === 'payout.completed';
    }

    public function isPayoutFailed(): bool
    {
        return $this->eventType === 'payout.failed';
    }

    // ── Data accessors ──

    /**
     * Get the payment reference
     */
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
