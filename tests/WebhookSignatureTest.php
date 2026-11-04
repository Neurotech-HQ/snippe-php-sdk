<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\SnippeException;
use Snippe\Webhook;

class WebhookSignatureTest extends TestCase
{
    private string $secret = 'test_webhook_secret_123';

    public function test_valid_signature_passes(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123', 'status' => 'completed']]);
        $event = Webhook::fromRaw($body, $this->signed($body, [
            'X-Webhook-Event' => 'payment.completed',
        ]), $this->secret);

        $this->assertTrue($event->isValid($this->secret));
        $this->assertTrue($event->isPaymentCompleted());
    }

    public function test_wrong_secret_fails(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $event = Webhook::fromRaw($body, $this->signed($body), $this->secret);

        $this->assertFalse($event->isValid('different-secret'));
    }

    public function test_missing_signature_fails(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Missing webhook signature');

        Webhook::fromRaw('{}', ['X-Webhook-Event' => 'payment.completed'], $this->secret);
    }

    public function test_tampered_body_fails(): void
    {
        $originalBody = json_encode(['data' => ['reference' => 'ref-123', 'amount' => 5000]]);
        $tamperedBody = json_encode(['data' => ['reference' => 'ref-123', 'amount' => 999999]]);

        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(401);

        Webhook::fromRaw($tamperedBody, $this->signed($originalBody), $this->secret);
    }

    public function test_body_only_signature_is_rejected(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $timestamp = (string) time();

        $this->expectException(SnippeException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        Webhook::fromRaw($body, [
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Signature' => hash_hmac('sha256', $body, $this->secret),
        ], $this->secret);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $timestamp = (string) (time() - 301);

        $this->expectException(SnippeException::class);
        $this->expectExceptionMessage('Webhook timestamp expired');

        Webhook::fromRaw($body, [
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Signature' => hash_hmac('sha256', $timestamp . '.' . $body, $this->secret),
        ], $this->secret);
    }

    public function test_verify_returns_self_on_valid(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $event = Webhook::fromRaw($body, $this->signed($body), $this->secret);

        $this->assertSame($event, $event->verify($this->secret));
    }

    public function test_verify_throws_on_invalid(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $event = Webhook::fromRaw($body, $this->signed($body), $this->secret);

        $this->expectException(SnippeException::class);
        $this->expectExceptionMessage('Invalid webhook signature');
        $event->verify('wrong-secret');
    }

    public function test_signature_header_case_insensitive(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);
        $headers = $this->signed($body);
        $headers['x-webhook-signature'] = strtoupper($headers['X-Webhook-Signature']);
        unset($headers['X-Webhook-Signature']);

        $event = Webhook::fromRaw($body, $headers, $this->secret);

        $this->assertTrue($event->isValid($this->secret));
    }

    public function test_payout_completed_event(): void
    {
        $body = json_encode(['data' => ['reference' => 'payout-ref']]);
        $event = Webhook::fromRaw($body, $this->signed($body, [
            'X-Webhook-Event' => 'payout.completed',
        ]), $this->secret);

        $this->assertTrue($event->isPayoutCompleted());
        $this->assertFalse($event->isPayoutFailed());
        $this->assertFalse($event->isPaymentCompleted());
    }

    public function test_payout_failed_event(): void
    {
        $body = json_encode(['data' => ['reference' => 'payout-ref']]);
        $event = Webhook::fromRaw($body, $this->signed($body, [
            'X-Webhook-Event' => 'payout.failed',
        ]), $this->secret);

        $this->assertTrue($event->isPayoutFailed());
        $this->assertFalse($event->isPayoutCompleted());
    }

    private function signed(string $body, array $headers = []): array
    {
        $timestamp = (string) time();
        $headers['X-Webhook-Timestamp'] = $timestamp;
        $headers['X-Webhook-Signature'] = hash_hmac('sha256', $timestamp . '.' . $body, $this->secret);

        return $headers;
    }
}
