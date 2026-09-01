<?php

namespace Snippe;

/**
 * Fluent builder for creating Snippe payouts.
 *
 * Usage:
 *   $snippe->mobileMoneyPayout(5000, '0754123456', 'John Doe')
 *       ->narration('Salary payment')
 *       ->send();
 *
 *   $snippe->bankPayout(5000, 'CRDB', '0200000000', 'John Doe')
 *       ->narration('Invoice payment')
 *       ->send();
 */
class PayoutBuilder
{
    private Snippe $client;
    private array $payload = [];
    private ?string $idempotencyKey = null;

    public function __construct(Snippe $client)
    {
        $this->client = $client;
    }

    // ── Core fields ──

    public function channel(string $channel): self
    {
        $this->payload['channel'] = $channel;
        return $this;
    }

    public function amount(int $amount): self
    {
        $this->payload['amount'] = $amount;
        return $this;
    }

    public function recipientPhone(string $phone): self
    {
        $this->payload['recipient_phone'] = PaymentBuilder::normalizePhone($phone);
        return $this;
    }

    public function recipientBank(string $bank, string $account): self
    {
        $this->payload['recipient_bank'] = $bank;
        $this->payload['recipient_account'] = $account;
        return $this;
    }

    public function recipientName(string $name): self
    {
        $this->payload['recipient_name'] = $name;
        return $this;
    }

    public function narration(string $narration): self
    {
        $this->payload['narration'] = $narration;
        return $this;
    }

    // ── URLs ──

    public function webhook(string $url): self
    {
        $this->payload['webhook_url'] = $url;
        return $this;
    }

    // ── Optional ──

    public function metadata(array $data): self
    {
        $this->payload['metadata'] = $data;
        return $this;
    }

    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    // ── Execute ──

    public function send(): Payout
    {
        if (!isset($this->payload['webhook_url']) && $this->client->getWebhookUrl()) {
            $this->payload['webhook_url'] = $this->client->getWebhookUrl();
        }

        $key = $this->idempotencyKey ?? uniqid('snp_po_', true);

        $response = $this->client->request(
            'POST',
            '/payouts/send',
            $this->payload,
            ['Idempotency-Key: ' . $key]
        );

        return new Payout($response['data'] ?? $response);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
