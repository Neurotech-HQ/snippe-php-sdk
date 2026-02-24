<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\PaymentBuilder;

class PhoneNormalizationTest extends TestCase
{
    /**
     * @dataProvider phoneProvider
     */
    public function test_normalizes_phone_numbers(string $input, string $expected): void
    {
        $this->assertEquals($expected, PaymentBuilder::normalizePhone($input));
    }

    public static function phoneProvider(): array
    {
        return [
            'starts with 0'        => ['0754123456', '255754123456'],
            'starts with +255'     => ['+255754123456', '255754123456'],
            'already 255'          => ['255754123456', '255754123456'],
            'just 9 digits'        => ['754123456', '255754123456'],
            'with spaces'          => ['0754 123 456', '255754123456'],
            'with dashes'          => ['0754-123-456', '255754123456'],
            'with parens'          => ['(0754) 123456', '255754123456'],
            'mpesa format'         => ['+255713456789', '255713456789'],
            'halotel format'       => ['0622123456', '255622123456'],
        ];
    }
}
