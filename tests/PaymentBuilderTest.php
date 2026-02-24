<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Snippe;
use Snippe\PaymentBuilder;

class PaymentBuilderTest extends TestCase
{
    private Snippe $snippe;

    protected function setUp(): void
    {
        $this->snippe = new Snippe('snp_test_key_123');
    }

    public function test_builds_mobile_money_payload(): void
    {
        $builder = $this->snippe->mobileMoney(5000, '0754123456')
            ->customer('John Doe', 'john@example.com')
            ->webhook('https://example.com/webhook')
            ->metadata(['order_id' => 'ORD-001']);

        $payload = $builder->toArray();

        $this->assertEquals('mobile', $payload['payment_type']);
        $this->assertEquals(5000, $payload['details']['amount']);
        $this->assertEquals('TZS', $payload['details']['currency']);
        $this->assertEquals('255754123456', $payload['phone_number']);
        $this->assertEquals('John', $payload['customer']['firstname']);
        $this->assertEquals('Doe', $payload['customer']['lastname']);
        $this->assertEquals('john@example.com', $payload['customer']['email']);
        $this->assertEquals('https://example.com/webhook', $payload['webhook_url']);
        $this->assertEquals(['order_id' => 'ORD-001'], $payload['metadata']);
    }

    public function test_builds_card_payment_payload(): void
    {
        $builder = $this->snippe->card(10000)
            ->phone('0754123456')
            ->customer('Jane', 'jane@example.com', 'Smith')
            ->billing('123 Main St', 'Dar es Salaam', 'DSM', '14101', 'TZ')
            ->redirectTo('https://example.com/success', 'https://example.com/cancel');

        $payload = $builder->toArray();

        $this->assertEquals('card', $payload['payment_type']);
        $this->assertEquals(10000, $payload['details']['amount']);
        $this->assertEquals('https://example.com/success', $payload['details']['redirect_url']);
        $this->assertEquals('https://example.com/cancel', $payload['details']['cancel_url']);
        $this->assertEquals('Jane', $payload['customer']['firstname']);
        $this->assertEquals('Smith', $payload['customer']['lastname']);
        $this->assertEquals('123 Main St', $payload['customer']['address']);
        $this->assertEquals('Dar es Salaam', $payload['customer']['city']);
        $this->assertEquals('TZ', $payload['customer']['country']);
    }

    public function test_builds_qr_payment_payload(): void
    {
        $builder = $this->snippe->qr(5000)
            ->customer('Ali Hassan', 'ali@example.com')
            ->description('Test payment');

        $payload = $builder->toArray();

        $this->assertEquals('dynamic-qr', $payload['payment_type']);
        $this->assertEquals(5000, $payload['details']['amount']);
        $this->assertEquals('Test payment', $payload['details']['description']);
        $this->assertEquals('Ali', $payload['customer']['firstname']);
        $this->assertEquals('Hassan', $payload['customer']['lastname']);
    }

    public function test_auto_splits_full_name(): void
    {
        $builder = $this->snippe->mobileMoney(1000, '0754000000')
            ->customer('John Doe', 'john@example.com');

        $payload = $builder->toArray();

        $this->assertEquals('John', $payload['customer']['firstname']);
        $this->assertEquals('Doe', $payload['customer']['lastname']);
    }

    public function test_handles_single_name(): void
    {
        $builder = $this->snippe->mobileMoney(1000, '0754000000')
            ->customer('Madonna', 'madonna@example.com');

        $payload = $builder->toArray();

        $this->assertEquals('Madonna', $payload['customer']['firstname']);
        $this->assertEquals('', $payload['customer']['lastname']);
    }

    public function test_explicit_first_last_name(): void
    {
        $builder = $this->snippe->mobileMoney(1000, '0754000000')
            ->customer('John', 'john@example.com', 'Doe');

        $payload = $builder->toArray();

        $this->assertEquals('John', $payload['customer']['firstname']);
        $this->assertEquals('Doe', $payload['customer']['lastname']);
    }

    public function test_uses_default_webhook_url(): void
    {
        $snippe = new Snippe('snp_test_key');
        $snippe->setWebhookUrl('https://default-webhook.com/hook');

        $this->assertEquals('https://default-webhook.com/hook', $snippe->getWebhookUrl());
    }

    public function test_custom_idempotency_key(): void
    {
        // Just verify the builder accepts and stores it without error
        $builder = $this->snippe->mobileMoney(5000, '0754123456')
            ->customer('Test User', 'test@example.com')
            ->idempotencyKey('order-123-attempt-1');

        $payload = $builder->toArray();

        // The key is used at send() time, not in payload
        $this->assertEquals('mobile', $payload['payment_type']);
    }

    public function test_amount_with_custom_currency(): void
    {
        $builder = (new PaymentBuilder($this->snippe))
            ->type('mobile')
            ->amount(100, 'USD')
            ->phone('0754123456');

        $payload = $builder->toArray();

        $this->assertEquals(100, $payload['details']['amount']);
        $this->assertEquals('USD', $payload['details']['currency']);
    }
}
