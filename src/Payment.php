<?php

namespace Snippe;

/**
 * Represents a Snippe payment response.
 *
 * Usage:
 *   $payment->reference()   // "9015c155-9e29-..."
 *   $payment->status()      // "pending"
 *   $payment->isPending()   // true
 *   $payment->paymentUrl()  // card/QR checkout URL
 *   $payment->qrCode()      // QR code data string
 */
class Payment
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

    public function paymentType(): ?string
    {
        return $this->data['payment_type'] ?? null;
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

    public function isExpired(): bool
    {
        return $this->status() === 'expired';
    }

    public function isVoided(): bool
    {
        return $this->status() === 'voided';
    }

    // ── Money ──

    public function amount(): ?int
    {
        return $this->data['amount']['value'] ?? null;
    }

    public function currency(): ?string
    {
        return $this->data['amount']['currency'] ?? null;
    }

    // ── Settlement (after completion) ──

    public function fees(): ?int
    {
        return $this->data['settlement']['fees']['value'] ?? null;
    }

    public function netAmount(): ?int
    {
        return $this->data['settlement']['net']['value'] ?? null;
    }

    // ── Card / QR fields ──

    public function paymentUrl(): ?string
    {
        return $this->data['payment_url'] ?? null;
    }

    public function qrCode(): ?string
    {
        return $this->data['payment_qr_code'] ?? null;
    }

    public function paymentToken(): ?string
    {
        return $this->data['payment_token'] ?? null;
    }

    // ── Timestamps ──

    public function expiresAt(): ?string
    {
        return $this->data['expires_at'] ?? null;
    }

    public function createdAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function completedAt(): ?string
    {
        return $this->data['completed_at'] ?? null;
    }

    // ── Customer ──

    public function customer(): array
    {
        return $this->data['customer'] ?? [];
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
