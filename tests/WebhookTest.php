<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\SnippeException;
use Snippe\Webhook;

class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    public function test_parses_verified_payment_completed_event(): void
    {
        $body = json_encode([
            'data' => [
                'reference' => '9015c155-9e29-4e8e-8fe6-d5d81553c8e6',
                'status' => 'completed',
                'amount' => ['value' => 5000, 'currency' => 'TZS'],
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ],
                'metadata' => ['order_id' => 'ORD-001'],
            ],
        ]);

        $event = $this->webhook($body, ['X-Webhook-Event' => 'payment.completed']);

        $this->assertTrue($event->isPaymentCompleted());
        $this->assertFalse($event->isPaymentFailed());
        $this->assertSame('payment.completed', $event->eventType());
        $this->assertSame('9015c155-9e29-4e8e-8fe6-d5d81553c8e6', $event->reference());
        $this->assertSame('completed', $event->status());
        $this->assertSame(5000, $event->amount());
        $this->assertSame('TZS', $event->currency());
        $this->assertSame(['order_id' => 'ORD-001'], $event->metadata());
    }

    public function test_parses_payment_failed_event(): void
    {
        $body = json_encode(['data' => ['reference' => 'abc-123', 'status' => 'failed']]);
        $event = $this->webhook($body, ['X-Webhook-Event' => 'payment.failed']);

        $this->assertTrue($event->isPaymentFailed());
        $this->assertFalse($event->isPaymentCompleted());
        $this->assertSame('abc-123', $event->reference());
    }

    public function test_accepts_case_insensitive_headers(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123', 'status' => 'completed']]);
        $signature = $this->signature($body);

        $event = Webhook::fromRaw($body, [
            'X-WEBHOOK-EVENT' => 'payment.completed',
            'X-SNIPPE-SIGNATURE' => strtoupper($signature),
        ], self::SECRET);

        $this->assertTrue($event->isPaymentCompleted());
    }

    public function test_does_not_trust_event_header_when_payload_status_disagrees(): void
    {
        $body = json_encode(['data' => ['status' => 'failed']]);
        $event = $this->webhook($body, ['X-Webhook-Event' => 'payment.completed']);

        $this->assertFalse($event->isPaymentCompleted());
        $this->assertSame('failed', $event->status());
    }

    public function test_rejects_missing_signature(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Missing webhook signature');

        Webhook::fromRaw('{}', [], self::SECRET);
    }

    public function test_rejects_malformed_signature(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Invalid webhook signature format');

        Webhook::fromRaw('{}', ['X-Snippe-Signature' => 'not-a-signature'], self::SECRET);
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Invalid webhook signature');

        Webhook::fromRaw('{}', [
            'X-Snippe-Signature' => 'sha256=' . str_repeat('0', 64),
        ], self::SECRET);
    }

    public function test_rejects_signature_for_modified_body(): void
    {
        $original = json_encode(['data' => ['status' => 'pending']]);
        $modified = json_encode(['data' => ['status' => 'completed']]);

        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(401);

        Webhook::fromRaw($modified, [
            'X-Snippe-Signature' => $this->signature($original),
        ], self::SECRET);
    }

    public function test_rejects_empty_secret(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Webhook secret must not be empty');

        Webhook::fromRaw('{}', ['X-Snippe-Signature' => $this->signature('{}')], '');
    }

    public function test_rejects_invalid_json_after_valid_signature(): void
    {
        $body = 'not valid json';

        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid webhook payload');

        $this->webhook($body);
    }

    public function test_rejects_scalar_json_payload(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Webhook payload must be a JSON object or array');

        $this->webhook('"scalar"');
    }

    public function test_preserves_raw_body(): void
    {
        $body = json_encode(['data' => ['reference' => 'test']]);
        $event = $this->webhook($body);

        $this->assertSame($body, $event->rawBody());
    }

    public function test_handles_missing_event_header(): void
    {
        $event = $this->webhook('{}');

        $this->assertSame('', $event->eventType());
        $this->assertFalse($event->isPaymentCompleted());
        $this->assertFalse($event->isPaymentFailed());
    }

    public function test_handles_malformed_optional_fields_safely(): void
    {
        $body = json_encode([
            'data' => [
                'reference' => 123,
                'status' => false,
                'amount' => 'not-an-integer',
                'customer' => 'not-an-array',
                'metadata' => null,
            ],
        ]);

        $event = $this->webhook($body);

        $this->assertNull($event->reference());
        $this->assertNull($event->status());
        $this->assertNull($event->amount());
        $this->assertNull($event->currency());
        $this->assertSame([], $event->customer());
        $this->assertSame([], $event->metadata());
    }

    public function test_falls_back_to_root_reference(): void
    {
        $body = json_encode(['reference' => 'root-ref-123']);
        $event = $this->webhook($body);

        $this->assertSame('root-ref-123', $event->reference());
    }

    private function webhook(string $body, array $headers = []): Webhook
    {
        $headers['X-Snippe-Signature'] = $this->signature($body);

        return Webhook::fromRaw($body, $headers, self::SECRET);
    }

    private function signature(string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, self::SECRET);
    }
}
