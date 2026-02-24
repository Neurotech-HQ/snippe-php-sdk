<?php

namespace Snippe;

/**
 * Handles incoming Snippe webhook events.
 *
 * Usage in your webhook.php:
 *
 *   require 'vendor/autoload.php';
 *   use Snippe\Webhook;
 *
 *   $event = Webhook::capture();
 *
 *   if ($event->isPaymentCompleted()) {
 *       $ref = $event->reference();
 *       // Update your order to "paid"...
 *   }
 *
 *   if ($event->isPaymentFailed()) {
 *       // Handle failure...
 *   }
 *
 *   $event->ok(); // Always respond 200
 */
class Webhook
{
    private array $payload;
    private array $headers;
    private string $eventType;
    private string $rawBody;

    private function __construct(array $payload, array $headers, string $rawBody = '')
    {
        $this->payload = $payload;
        $this->rawBody = $rawBody;

        // Normalize headers to lowercase for reliable lookup
        $this->headers = array_change_key_case($headers, CASE_LOWER);

        // Extract event type from header (case-insensitive)
        $this->eventType = $this->headers['x-webhook-event'] ?? '';
    }

    // ── Factory methods ──

    /**
     * Capture the current incoming webhook request.
     * Call this in your webhook endpoint.
     *
     * @throws SnippeException if the payload isn't valid JSON
     */
    public static function capture(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : self::parseHeaders();
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            throw new SnippeException(
                'Invalid webhook payload: ' . json_last_error_msg(),
                400
            );
        }

        return new self($payload ?? [], $headers, $raw);
    }

    /**
     * Create from raw data (useful for testing)
     */
    public static function fromRaw(string $body, array $headers = []): self
    {
        $payload = json_decode($body, true);

        if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new SnippeException('Invalid webhook payload', 400);
        }

        return new self($payload ?? [], $headers, $body);
    }

    // ── Event type checks ──

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function isPaymentCompleted(): bool
    {
        return $this->eventType === 'payment.completed';
    }

    public function isPaymentFailed(): bool
    {
        return $this->eventType === 'payment.failed';
    }

    // ── Data accessors ──

    /**
     * Get the payment reference
     */
    public function reference(): ?string
    {
        return $this->payload['data']['reference']
            ?? $this->payload['reference']
            ?? null;
    }

    /**
     * Get the payment status from payload
     */
    public function status(): ?string
    {
        return $this->payload['data']['status']
            ?? $this->payload['status']
            ?? null;
    }

    /**
     * Get the payment amount
     */
    public function amount(): ?int
    {
        return $this->payload['data']['amount']['value']
            ?? $this->payload['data']['amount']
            ?? null;
    }

    /**
     * Get the currency
     */
    public function currency(): ?string
    {
        return $this->payload['data']['amount']['currency'] ?? null;
    }

    /**
     * Get customer info
     */
    public function customer(): array
    {
        return $this->payload['data']['customer'] ?? [];
    }

    /**
     * Get custom metadata
     */
    public function metadata(): array
    {
        return $this->payload['data']['metadata'] ?? [];
    }

    /**
     * Get the full webhook payload
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Get the raw JSON body
     */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    // ── Response helpers ──

    /**
     * Respond 200 OK to Snippe (call this when you're done processing)
     */
    public function ok(): void
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    /**
     * Respond with an error status
     */
    public function fail(int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false]);
    }

    // ── Helpers ──

    /**
     * Fallback header parser for environments without getallheaders()
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
