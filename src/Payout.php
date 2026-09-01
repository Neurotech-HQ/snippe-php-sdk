<?php

namespace Snippe;

/**
 * Represents a Snippe payout response.
 *
 * Usage:
 *   $payout->reference()   // "667c9279-..."
 *   $payout->status()      // "pending"
 *   $payout->isPending()   // true
 *   $payout->fees()        // fee amount
 *   $payout->total()       // total deducted (amount + fees)
 */
class Payout
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // ── Identity ──

    public function reference(): ?string
    {
        return $this->data['reference'] ?? null;
    }

    public function channel(): ?string
    {
        return $this->data['channel'] ?? null;
    }

    // ── Status ──

    public function status(): ?string
    {
        return $this->data['status'] ?? null;
    }

    public function isPending(): bool
    {
        return $this->status() === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status() === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status() === 'failed';
    }

    public function isReversed(): bool
    {
        return $this->status() === 'reversed';
    }

    // ── Money ──

    public function amount(): ?int
    {
        return $this->data['amount']['value']
            ?? $this->data['amount']
            ?? null;
    }

    public function currency(): ?string
    {
        return $this->data['amount']['currency']
            ?? $this->data['currency']
            ?? null;
    }

    public function fees(): ?int
    {
        return $this->data['fees']['value']
            ?? $this->data['fees']
            ?? null;
    }

    public function total(): ?int
    {
        return $this->data['total']['value']
            ?? $this->data['total']
            ?? null;
    }

    // ── Recipient ──

    public function recipientName(): ?string
    {
        return $this->data['recipient_name'] ?? null;
    }

    public function recipientPhone(): ?string
    {
        return $this->data['recipient_phone'] ?? null;
    }

    public function recipientBank(): ?string
    {
        return $this->data['recipient_bank'] ?? null;
    }

    public function recipientAccount(): ?string
    {
        return $this->data['recipient_account'] ?? null;
    }

    // ── Timestamps ──

    public function createdAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function completedAt(): ?string
    {
        return $this->data['completed_at'] ?? null;
    }

    // ── Raw access ──

    public function toArray(): array
    {
        return $this->data;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
