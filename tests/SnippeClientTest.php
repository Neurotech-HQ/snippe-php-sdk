<?php

namespace Snippe\Tests;

use PHPUnit\Framework\TestCase;
use Snippe\Snippe;
use Snippe\SnippeException;

class SnippeClientTest extends TestCase
{
    public function test_throws_on_empty_api_key(): void
    {
        $this->expectException(SnippeException::class);
        $this->expectExceptionMessage('API key is required');

        new Snippe('');
    }

    public function test_creates_client_with_valid_key(): void
    {
        $snippe = new Snippe('snp_test_key');

        $this->assertInstanceOf(Snippe::class, $snippe);
    }

    public function test_sets_default_webhook_url(): void
    {
        $snippe = new Snippe('snp_test_key');
        $snippe->setWebhookUrl('https://example.com/webhook');

        $this->assertEquals('https://example.com/webhook', $snippe->getWebhookUrl());
    }

    public function test_chaining_configuration(): void
    {
        $snippe = (new Snippe('snp_test_key'))
            ->setWebhookUrl('https://example.com/webhook')
            ->setTimeout(60);

        $this->assertInstanceOf(Snippe::class, $snippe);
    }

    public function test_mobile_money_returns_builder(): void
    {
        $snippe = new Snippe('snp_test_key');
        $builder = $snippe->mobileMoney(5000, '0754123456');

        $this->assertInstanceOf(\Snippe\PaymentBuilder::class, $builder);

        $payload = $builder->toArray();
        $this->assertEquals('mobile', $payload['payment_type']);
        $this->assertEquals(5000, $payload['details']['amount']);
    }

    public function test_card_returns_builder(): void
    {
        $snippe = new Snippe('snp_test_key');
        $builder = $snippe->card(10000);

        $payload = $builder->toArray();
        $this->assertEquals('card', $payload['payment_type']);
        $this->assertEquals(10000, $payload['details']['amount']);
    }

    public function test_qr_returns_builder(): void
    {
        $snippe = new Snippe('snp_test_key');
        $builder = $snippe->qr(3000);

        $payload = $builder->toArray();
        $this->assertEquals('dynamic-qr', $payload['payment_type']);
        $this->assertEquals(3000, $payload['details']['amount']);
    }

    public function test_request_to_invalid_url_throws(): void
    {
        $snippe = new Snippe('snp_test_key');
        $snippe->setBaseUrl('http://localhost:1'); // nothing running here
        $snippe->setTimeout(2);

        $this->expectException(SnippeException::class);

        $snippe->find('some-reference');
    }

    public function test_exception_contains_response_data(): void
    {
        $response = [
            'status' => 'error',
            'code' => 401,
            'error_code' => 'unauthorized',
            'message' => 'invalid or missing API key',
        ];

        $exception = new SnippeException('invalid or missing API key', 401, $response);

        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals('unauthorized', $exception->getErrorCode());
        $this->assertEquals($response, $exception->getResponse());
    }
}
