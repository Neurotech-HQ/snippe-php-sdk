<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Webhook;
use Snippe\SnippeException;

class WebhookTest extends TestCase
{
    public function test_parses_payment_completed_event(): void
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
            ]
        ]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Event' => 'payment.completed',
        ]);

        $this->assertTrue($event->isPaymentCompleted());
        $this->assertFalse($event->isPaymentFailed());
        $this->assertEquals('payment.completed', $event->eventType());
        $this->assertEquals('9015c155-9e29-4e8e-8fe6-d5d81553c8e6', $event->reference());
        $this->assertEquals('completed', $event->status());
        $this->assertEquals(5000, $event->amount());
        $this->assertEquals('TZS', $event->currency());
        $this->assertEquals(['order_id' => 'ORD-001'], $event->metadata());
    }

    public function test_parses_payment_failed_event(): void
    {
        $body = json_encode([
            'data' => [
                'reference' => 'abc-123',
                'status' => 'failed',
            ]
        ]);

        $event = Webhook::fromRaw($body, [
            'X-Webhook-Event' => 'payment.failed',
        ]);

        $this->assertTrue($event->isPaymentFailed());
        $this->assertFalse($event->isPaymentCompleted());
        $this->assertEquals('abc-123', $event->reference());
    }

    public function test_handles_lowercase_headers(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);

        $event = Webhook::fromRaw($body, [
            'x-webhook-event' => 'payment.completed',
        ]);

        $this->assertTrue($event->isPaymentCompleted());
    }

    public function test_handles_uppercase_headers(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);

        $event = Webhook::fromRaw($body, [
            'X-WEBHOOK-EVENT' => 'payment.completed',
        ]);

        $this->assertTrue($event->isPaymentCompleted());
    }

    public function test_handles_missing_event_header(): void
    {
        $body = json_encode(['data' => ['reference' => 'ref-123']]);

        $event = Webhook::fromRaw($body, []);

        $this->assertEquals('', $event->eventType());
        $this->assertFalse($event->isPaymentCompleted());
        $this->assertFalse($event->isPaymentFailed());
    }

    public function test_throws_on_invalid_json(): void
    {
        $this->expectException(SnippeException::class);

        Webhook::fromRaw('not valid json', []);
    }

    public function test_handles_empty_json_object(): void
    {
        $event = Webhook::fromRaw('{}', [
            'X-Webhook-Event' => 'payment.completed',
        ]);

        $this->assertTrue($event->isPaymentCompleted());
        $this->assertNull($event->reference());
        $this->assertNull($event->amount());
    }

    public function test_raw_body_preserved(): void
    {
        $body = json_encode(['data' => ['reference' => 'test']]);

        $event = Webhook::fromRaw($body, []);

        $this->assertEquals($body, $event->rawBody());
    }

    public function test_customer_data(): void
    {
        $body = json_encode([
            'data' => [
                'customer' => [
                    'first_name' => 'Ali',
                    'last_name' => 'Hassan',
                    'email' => 'ali@example.com',
                    'phone' => '+255754123456',
                ]
            ]
        ]);

        $event = Webhook::fromRaw($body, []);

        $customer = $event->customer();
        $this->assertEquals('Ali', $customer['first_name']);
        $this->assertEquals('ali@example.com', $customer['email']);
    }

    public function test_fallback_reference_from_root(): void
    {
        // Some APIs put reference at root level
        $body = json_encode([
            'reference' => 'root-ref-123',
        ]);

        $event = Webhook::fromRaw($body, []);

        $this->assertEquals('root-ref-123', $event->reference());
    }
}
