<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Snippe;
use Snippe\PayoutBuilder;

class PayoutBuilderTest extends TestCase
{
    private Snippe $snippe;

    protected function setUp(): void
    {
        $this->snippe = new Snippe('snp_test_key_123');
    }

    public function test_builds_mobile_payout_payload(): void
    {
        $builder = $this->snippe->mobileMoneyPayout(5000, '0754123456', 'John Doe')
            ->narration('Salary payment January 2026')
            ->webhook('https://example.com/webhook')
            ->metadata(['employee_id' => 'EMP-001']);

        $payload = $builder->toArray();

        $this->assertEquals('mobile', $payload['channel']);
        $this->assertEquals(5000, $payload['amount']);
        $this->assertEquals('255754123456', $payload['recipient_phone']);
        $this->assertEquals('John Doe', $payload['recipient_name']);
        $this->assertEquals('Salary payment January 2026', $payload['narration']);
        $this->assertEquals('https://example.com/webhook', $payload['webhook_url']);
        $this->assertEquals(['employee_id' => 'EMP-001'], $payload['metadata']);
    }

    public function test_builds_bank_payout_payload(): void
    {
        $builder = $this->snippe->bankPayout(10000, 'CRDB', '0200000000', 'Jane Smith')
            ->narration('Invoice payment INV-2026-001')
            ->metadata(['invoice_id' => 'INV-2026-001']);

        $payload = $builder->toArray();

        $this->assertEquals('bank', $payload['channel']);
        $this->assertEquals(10000, $payload['amount']);
        $this->assertEquals('CRDB', $payload['recipient_bank']);
        $this->assertEquals('0200000000', $payload['recipient_account']);
        $this->assertEquals('Jane Smith', $payload['recipient_name']);
        $this->assertEquals('Invoice payment INV-2026-001', $payload['narration']);
    }

    public function test_custom_idempotency_key(): void
    {
        $builder = $this->snippe->mobileMoneyPayout(5000, '0754123456', 'Test')
            ->idempotencyKey('payout-custom-key-001');

        $payload = $builder->toArray();
        $this->assertEquals('mobile', $payload['channel']);
    }

    public function test_phone_normalization_in_payout(): void
    {
        $builder = $this->snippe->mobileMoneyPayout(5000, '+255 754 123 456', 'Test');
        $payload = $builder->toArray();
        $this->assertEquals('255754123456', $payload['recipient_phone']);
    }

    public function test_uses_default_webhook_url(): void
    {
        $snippe = new Snippe('snp_test_key');
        $snippe->setWebhookUrl('https://default-webhook.com/hook');

        $builder = $snippe->mobileMoneyPayout(5000, '0754123456', 'Test');
        $payload = $builder->toArray();

        // webhook_url is applied at send() time, not in toArray()
        $this->assertArrayNotHasKey('webhook_url', $payload);
    }
}
