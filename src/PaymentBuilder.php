<?php

namespace Snippe;

/**
 * Fluent builder for creating Snippe payments.
 *
 * Usage:
 *   $snippe->mobileMoney(5000, '0754123456')
 *       ->customer('John Doe', 'john@email.com')
 *       ->webhook('https://yoursite.com/webhook')
 *       ->send();
 */
class PaymentBuilder
{
    private Snippe $client;
    private array $payload = [];
    private ?string $idempotencyKey = null;

    public function __construct(Snippe $client)
    {
        $this->client = $client;
    }

    // ── Core fields ──

    /**
     * Set payment type: "mobile", "card", "dynamic-qr"
     */
    public function type(string $type): self
    {
        $this->payload['payment_type'] = $type;
        return $this;
    }

    /**
     * Set payment amount and currency
     */
    public function amount(int $amount, string $currency = 'TZS'): self
    {
        $this->payload['details']['amount'] = $amount;
        $this->payload['details']['currency'] = $currency;
        return $this;
    }

    /**
     * Set customer phone number (auto-normalizes to 255XXXXXXXXX)
     *
     * Accepts: "0754123456", "+255754123456", "255754123456", "754123456"
     */
    public function phone(string $phone): self
    {
        $this->payload['phone_number'] = self::normalizePhone($phone);
        return $this;
    }

    /**
     * Set customer info.
     *
     * Accepts full name (auto-split) or first + last separately:
     *   ->customer('John Doe', 'john@email.com')
     *   ->customer('John', 'john@email.com', 'Doe')
     */
    public function customer(string $firstName, string $email, ?string $lastName = null): self
    {
        // If no lastName provided, try to split firstName as "Full Name"
        if ($lastName === null) {
            $parts = explode(' ', trim($firstName), 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }

        $this->payload['customer'] = array_merge(
            $this->payload['customer'] ?? [],
            [
                'firstname' => $firstName,
                'lastname'  => $lastName,
                'email'     => $email,
            ]
        );

        return $this;
    }

    /**
     * Set billing address (required for card payments)
     */
    public function billing(string $address, string $city, string $state, string $postcode, string $country = 'TZ'): self
    {
        $this->payload['customer'] = array_merge(
            $this->payload['customer'] ?? [],
            [
                'address'  => $address,
                'city'     => $city,
                'state'    => $state,
                'postcode' => $postcode,
                'country'  => $country,
            ]
        );

        return $this;
    }

    // ── URLs ──

    /**
     * Set webhook URL for payment notifications
     */
    public function webhook(string $url): self
    {
        $this->payload['webhook_url'] = $url;
        return $this;
    }

    /**
     * Set redirect URLs for card/QR payments
     */
    public function redirectTo(string $successUrl, string $cancelUrl): self
    {
        $this->payload['details']['redirect_url'] = $successUrl;
        $this->payload['details']['cancel_url'] = $cancelUrl;
        return $this;
    }

    // ── Optional ──

    /**
     * Add custom metadata (e.g. order_id)
     */
    public function metadata(array $data): self
    {
        $this->payload['metadata'] = $data;
        return $this;
    }

    /**
     * Set payment description
     */
    public function description(string $desc): self
    {
        $this->payload['details']['description'] = $desc;
        return $this;
    }

    /**
     * Set custom idempotency key (auto-generated if not set)
     */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    // ── Execute ──

    /**
     * Send the payment request to Snippe API
     *
     * @throws SnippeException on API errors
     */
    public function send(): Payment
    {
        // Apply default webhook from client if not set
        if (!isset($this->payload['webhook_url']) && $this->client->getWebhookUrl()) {
            $this->payload['webhook_url'] = $this->client->getWebhookUrl();
        }

        // Auto-generate idempotency key
        $key = $this->idempotencyKey ?? uniqid('snp_', true);

        $response = $this->client->request(
            'POST',
            '/payments',
            $this->payload,
            ['Idempotency-Key: ' . $key]
        );

        return new Payment($response['data'] ?? $response);
    }

    /**
     * Preview the payload without sending
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    // ── Helpers ──

    /**
     * Normalize a Tanzanian phone number to 255XXXXXXXXX format
     */
    public static function normalizePhone(string $phone): string
    {
        // Strip everything except digits and +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove leading +
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        // 0754... -> 255754...
        if (str_starts_with($phone, '0')) {
            $phone = '255' . substr($phone, 1);
        }

        // 754... (9 digits without country code) -> 255754...
        if (strlen($phone) === 9 && !str_starts_with($phone, '255')) {
            $phone = '255' . $phone;
        }

        return $phone;
    }
}
