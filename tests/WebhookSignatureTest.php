<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Webhook;
use Snippe\SnippeException;

class WebhookSignatureTest extends TestCase
{
    private string $secret = 'test_webhook_secret_123';

    public function test_valid_signature_passes(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $signature = hash_hmac('sha256', $body, $this->secret);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event' => 'payment.completed',
        ]);

        $this->assertTrue($event->isValid($this->secret));
    }

    public function test_invalid_signature_fails(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Signature' => 'invalid_signature_here',
            'X-Webhook-Event' => 'payment.completed',
        ]);

        $this->assertFalse($event->isValid($this->secret));
    }

    public function test_missing_signature_fails(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Event' => 'payment.completed',
        ]);

        $this->assertFalse($event->isValid($this->secret));
    }

    public function test_tampered_body_fails(): void
    {
        $originalBody = json_encode(['data' => ['reference' => 'ref-123', 'amount' => 5000]]);
        $signature = hash_hmac('sha256', $originalBody, $this->secret);

        $tamperedBody = json_encode(['data' => ['reference' => 'ref-123', 'amount' => 999999]]);

        $event = Webhook::fromRaw($tamperedBody, [
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event' => 'payment.completed',
        ]);

        $this->assertFalse($event->isValid($this->secret));
    }

    public function test_verify_returns_self_on_valid(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $signature = hash_hmac('sha256', $body, $this->secret);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Signature' => $signature,
        ]);

        $result = $event->verify($this->secret);
        $this->assertSame($event, $result);
    }

    public function test_verify_throws_on_invalid(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Signature' => 'wrong',
        ]);

        $this->expectException(SnippeException::class);
        $this->expectExceptionMessage('Invalid webhook signature');
        $event->verify($this->secret);
    }

    public function test_signature_header_case_insensitive(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $signature = hash_hmac('sha256', $body, $this->secret);

        $event = Webhook::fromRaw($body, [
            'x-webhook-signature' => $signature,
        ]);

        $this->assertTrue($event->isValid($this->secret));
    }

    public function test_payout_completed_event(): void
    {
        $body = json_encode(['data' => ['reference' => 'payout-ref']]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Event' => 'payout.completed',
        ]);

        $this->assertTrue($event->isPayoutCompleted());
        $this->assertFalse($event->isPayoutFailed());
        $this->assertFalse($event->isPaymentCompleted());
    }

    public function test_payout_failed_event(): void
    {
        $body = json_encode(['data' => ['reference' => 'payout-ref']]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Event' => 'payout.failed',
        ]);

        $this->assertTrue($event->isPayoutFailed());
        $this->assertFalse($event->isPayoutCompleted());
    }
}
