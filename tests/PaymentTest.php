<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Payment;

class PaymentTest extends TestCase
{
    public function test_parses_mobile_money_response(): void
    {
        $payment = new Payment([
            'amount' => ['currency' => 'TZS', 'value' => 5000],
            'api_version' => '2026-01-25',
            'expires_at' => '2026-01-25T05:04:54.063993853Z',
            'object' => 'payment',
            'payment_type' => 'mobile',
            'reference' => '9015c155-9e29-4e8e-8fe6-d5d81553c8e6',
            'status' => 'pending',
        ]);

        $this->assertEquals('9015c155-9e29-4e8e-8fe6-d5d81553c8e6', $payment->reference());
        $this->assertEquals('pending', $payment->status());
        $this->assertEquals('mobile', $payment->paymentType());
        $this->assertEquals(5000, $payment->amount());
        $this->assertEquals('TZS', $payment->currency());
        $this->assertTrue($payment->isPending());
        $this->assertFalse($payment->isCompleted());
        $this->assertFalse($payment->isFailed());
        $this->assertFalse($payment->isExpired());
        $this->assertNull($payment->paymentUrl());
        $this->assertNull($payment->qrCode());
    }

    public function test_parses_card_payment_response(): void
    {
        $payment = new Payment([
            'amount' => ['currency' => 'TZS', 'value' => 10000],
            'payment_type' => 'card',
            'payment_url' => 'https://tz.selcom.online/paymentgw/checkout/xyz',
            'payment_qr_code' => '000201010212...',
            'payment_token' => '63891931',
            'reference' => '2e0bcc5f-92ca-44f9-8c1b-4d2966d9921f',
            'status' => 'pending',
        ]);

        $this->assertEquals('card', $payment->paymentType());
        $this->assertEquals('https://tz.selcom.online/paymentgw/checkout/xyz', $payment->paymentUrl());
        $this->assertEquals('000201010212...', $payment->qrCode());
        $this->assertEquals('63891931', $payment->paymentToken());
    }

    public function test_parses_completed_payment_with_settlement(): void
    {
        $payment = new Payment([
            'amount' => ['currency' => 'TZS', 'value' => 5000],
            'status' => 'completed',
            'settlement' => [
                'fees' => ['currency' => 'TZS', 'value' => 90],
                'gross' => ['currency' => 'TZS', 'value' => 5000],
                'net' => ['currency' => 'TZS', 'value' => 4910],
            ],
            'reference' => 'ref-123',
            'completed_at' => '2026-01-25T00:50:44.105159Z',
        ]);

        $this->assertTrue($payment->isCompleted());
        $this->assertEquals(90, $payment->fees());
        $this->assertEquals(4910, $payment->netAmount());
        $this->assertEquals('2026-01-25T00:50:44.105159Z', $payment->completedAt());
    }

    public function test_dynamic_property_access(): void
    {
        $payment = new Payment([
            'reference' => 'ref-123',
            'object' => 'payment',
            'api_version' => '2026-01-25',
        ]);

        $this->assertEquals('payment', $payment->object);
        $this->assertEquals('2026-01-25', $payment->api_version);
        $this->assertNull($payment->nonexistent);
    }

    public function test_to_array(): void
    {
        $data = [
            'reference' => 'ref-123',
            'status' => 'pending',
            'amount' => ['value' => 5000, 'currency' => 'TZS'],
        ];

        $payment = new Payment($data);

        $this->assertEquals($data, $payment->toArray());
    }

    public function test_expired_status(): void
    {
        $payment = new Payment(['status' => 'expired']);

        $this->assertTrue($payment->isExpired());
        $this->assertFalse($payment->isPending());
    }

    public function test_failed_status(): void
    {
        $payment = new Payment(['status' => 'failed']);

        $this->assertTrue($payment->isFailed());
        $this->assertFalse($payment->isCompleted());
    }

    public function test_customer_data(): void
    {
        $payment = new Payment([
            'customer' => [
                'email' => 'test@example.com',
                'first_name' => 'Test',
            ],
        ]);

        $this->assertEquals('test@example.com', $payment->customer()['email']);
    }

    public function test_isset_on_dynamic_properties(): void
    {
        $payment = new Payment(['reference' => 'ref-123']);

        $this->assertTrue(isset($payment->reference));
        $this->assertFalse(isset($payment->missing_field));
    }
}
