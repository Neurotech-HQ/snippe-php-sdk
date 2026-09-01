<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Payout;

class PayoutTest extends TestCase
{
    public function test_parses_mobile_payout_response(): void
    {
        $payout = new Payout([
            'reference' => '667c9279-846f-4001-b046-fdecab204f4f',
            'status' => 'pending',
            'channel' => 'mobile',
            'amount' => ['value' => 5000, 'currency' => 'TZS'],
            'fees' => ['value' => 200, 'currency' => 'TZS'],
            'total' => ['value' => 5200, 'currency' => 'TZS'],
            'recipient_name' => 'John Doe',
            'recipient_phone' => '255781000000',
        ]);

        $this->assertEquals('667c9279-846f-4001-b046-fdecab204f4f', $payout->reference());
        $this->assertEquals('pending', $payout->status());
        $this->assertEquals('mobile', $payout->channel());
        $this->assertEquals(5000, $payout->amount());
        $this->assertEquals('TZS', $payout->currency());
        $this->assertEquals(200, $payout->fees());
        $this->assertEquals(5200, $payout->total());
        $this->assertEquals('John Doe', $payout->recipientName());
        $this->assertEquals('255781000000', $payout->recipientPhone());
        $this->assertTrue($payout->isPending());
        $this->assertFalse($payout->isCompleted());
    }

    public function test_parses_bank_payout_response(): void
    {
        $payout = new Payout([
            'reference' => 'ref-bank-001',
            'status' => 'completed',
            'channel' => 'bank',
            'amount' => ['value' => 10000, 'currency' => 'TZS'],
            'recipient_name' => 'Jane Smith',
            'recipient_bank' => 'CRDB',
            'recipient_account' => '0200000000',
            'completed_at' => '2026-01-25T12:00:00Z',
        ]);

        $this->assertEquals('bank', $payout->channel());
        $this->assertTrue($payout->isCompleted());
        $this->assertEquals('CRDB', $payout->recipientBank());
        $this->assertEquals('0200000000', $payout->recipientAccount());
        $this->assertEquals('2026-01-25T12:00:00Z', $payout->completedAt());
    }

    public function test_failed_status(): void
    {
        $payout = new Payout(['status' => 'failed']);
        $this->assertTrue($payout->isFailed());
        $this->assertFalse($payout->isPending());
    }

    public function test_reversed_status(): void
    {
        $payout = new Payout(['status' => 'reversed']);
        $this->assertTrue($payout->isReversed());
        $this->assertFalse($payout->isCompleted());
    }

    public function test_to_array(): void
    {
        $data = [
            'reference' => 'ref-123',
            'status' => 'pending',
            'amount' => ['value' => 5000, 'currency' => 'TZS'],
        ];

        $payout = new Payout($data);
        $this->assertEquals($data, $payout->toArray());
    }

    public function test_dynamic_property_access(): void
    {
        $payout = new Payout([
            'reference' => 'ref-123',
            'narration' => 'Salary payment',
        ]);

        $this->assertEquals('Salary payment', $payout->narration);
        $this->assertNull($payout->nonexistent);
        $this->assertTrue(isset($payout->reference));
        $this->assertFalse(isset($payout->missing));
    }
}
