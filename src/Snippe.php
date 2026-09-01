<?php

namespace Snippe;

/**
 * Snippe PHP SDK - Simple payments for Tanzania.
 *
 * Quick start:
 *
 *   $snippe = new Snippe('snp_your_api_key');
 *
 *   // Mobile Money
 *   $payment = $snippe->mobileMoney(5000, '0754123456')
 *       ->customer('John Doe', 'john@email.com')
 *       ->send();
 *
 *   // Card
 *   $payment = $snippe->card(10000)
 *       ->customer('John Doe', 'john@email.com')
 *       ->billing('123 Main St', 'Dar es Salaam', 'DSM', '14101')
 *       ->redirectTo('https://site.com/success', 'https://site.com/cancel')
 *       ->send();
 *
 *   // QR Code
 *   $payment = $snippe->qr(5000)
 *       ->customer('John Doe', 'john@email.com')
 *       ->send();
 *
 *   echo $payment->reference();  // "9015c155-..."
 *   echo $payment->status();     // "pending"
 *   echo $payment->paymentUrl(); // checkout URL (card/QR)
 */
class Snippe
{
    private string $apiKey;
    private string $baseUrl = 'https://api.snippe.sh/v1';
    private ?string $defaultWebhookUrl = null;
    private int $timeout = 30;

    public function __construct(string $apiKey)
    {
        if (empty($apiKey)) {
            throw new SnippeException('API key is required', 0);
        }

        $this->apiKey = $apiKey;
    }

    // ── Configuration ──

    /**
     * Set a default webhook URL for all payments
     */
    public function setWebhookUrl(string $url): self
    {
        $this->defaultWebhookUrl = $url;
        return $this;
    }

    /**
     * Set request timeout in seconds (default: 30)
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Override base URL (for testing)
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    // ── Payment Builders ──

    /**
     * Create a mobile money payment.
     *
     * $snippe->mobileMoney(5000, '0754123456')
     *     ->customer('John Doe', 'john@email.com')
     *     ->send();
     */
    public function mobileMoney(int $amount, string $phone): PaymentBuilder
    {
        return (new PaymentBuilder($this))
            ->type('mobile')
            ->amount($amount)
            ->phone($phone);
    }

    /**
     * Create a card payment.
     *
     * $snippe->card(10000)
     *     ->customer('John Doe', 'john@email.com')
     *     ->billing('123 St', 'DSM', 'DSM', '14101')
     *     ->redirectTo('/success', '/cancel')
     *     ->send();
     */
    public function card(int $amount): PaymentBuilder
    {
        return (new PaymentBuilder($this))
            ->type('card')
            ->amount($amount);
    }

    /**
     * Create a dynamic QR code payment.
     *
     * $payment = $snippe->qr(5000)
     *     ->customer('John Doe', 'john@email.com')
     *     ->send();
     *
     * echo $payment->qrCode(); // QR data to render
     */
    public function qr(int $amount): PaymentBuilder
    {
        return (new PaymentBuilder($this))
            ->type('dynamic-qr')
            ->amount($amount);
    }

    // ── Payout Builders ──

    /**
     * Create a mobile money payout.
     *
     * $snippe->mobileMoneyPayout(5000, '0754123456', 'John Doe')
     *     ->narration('Salary payment')
     *     ->send();
     */
    public function mobileMoneyPayout(int $amount, string $phone, string $recipientName): PayoutBuilder
    {
        return (new PayoutBuilder($this))
            ->channel('mobile')
            ->amount($amount)
            ->recipientPhone($phone)
            ->recipientName($recipientName);
    }

    /**
     * Create a bank transfer payout.
     *
     * $snippe->bankPayout(5000, 'CRDB', '0200000000', 'John Doe')
     *     ->narration('Invoice payment')
     *     ->send();
     */
    public function bankPayout(int $amount, string $bank, string $account, string $recipientName): PayoutBuilder
    {
        return (new PayoutBuilder($this))
            ->channel('bank')
            ->amount($amount)
            ->recipientBank($bank, $account)
            ->recipientName($recipientName);
    }

    // ── Payout Operations ──

    /**
     * Get a payout by reference.
     *
     * $payout = $snippe->findPayout('667c9279-...');
     * echo $payout->status(); // "completed"
     */
    public function findPayout(string $reference): Payout
    {
        $response = $this->request('GET', "/payouts/{$reference}");
        return new Payout($response['data'] ?? $response);
    }

    /**
     * List all payouts with pagination.
     *
     * $result = $snippe->payouts(limit: 20, offset: 0);
     * $items = $result['data']['items'];
     */
    public function payouts(int $limit = 20, int $offset = 0): array
    {
        return $this->request('GET', "/payouts?limit={$limit}&offset={$offset}");
    }

    /**
     * Calculate payout fee before sending.
     *
     * $fee = $snippe->payoutFee(5000);
     * echo $fee['data']['fee_amount'];
     */
    public function payoutFee(int $amount): array
    {
        return $this->request('GET', '/payouts/fee?amount=' . $amount);
    }

    // ── Payment Operations ──

    /**
     * Get a payment by reference.
     *
     * $payment = $snippe->find('9015c155-9e29-...');
     * echo $payment->status(); // "completed"
     */
    public function find(string $reference): Payment
    {
        $response = $this->request('GET', "/payments/{$reference}");
        return new Payment($response['data'] ?? $response);
    }

    /**
     * List all payments with pagination.
     *
     * $result = $snippe->payments(limit: 20, offset: 0);
     * $items = $result['data']['items'];
     */
    public function payments(int $limit = 20, int $offset = 0): array
    {
        return $this->request('GET', "/payments?limit={$limit}&offset={$offset}");
    }

    /**
     * Search payments by reference.
     */
    public function search(string $reference): array
    {
        return $this->request('GET', '/payments/search?reference=' . urlencode($reference));
    }

    /**
     * Get your account balance.
     *
     * $balance = $snippe->balance();
     * echo $balance['data']['available']['value']; // 6943
     */
    public function balance(): array
    {
        return $this->request('GET', '/payments/balance');
    }

    /**
     * Trigger/retry USSD push for a pending payment.
     *
     * $snippe->push('reference-id');
     * $snippe->push('reference-id', '+255787654321'); // different phone
     */
    public function push(string $reference, ?string $phone = null): array
    {
        $body = $phone ? ['phone' => PaymentBuilder::normalizePhone($phone)] : [];
        return $this->request('POST', "/payments/{$reference}/push", $body);
    }

    // ── Internal ──

    public function getWebhookUrl(): ?string
    {
        return $this->defaultWebhookUrl;
    }

    /**
     * Make an HTTP request to the Snippe API.
     *
     * @throws SnippeException on network or API errors
     */
    public function request(string $method, string $endpoint, array $body = [], array $extraHeaders = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = array_merge([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ], $extraHeaders);

        $ch = curl_init();

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($ch, $opts);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        // curl_close is a no-op since PHP 8.0, handle freed automatically

        // Network error
        if ($error) {
            throw new SnippeException("Network error: {$error}", 0);
        }

        // Parse response
        $decoded = json_decode($result, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new SnippeException(
                'Invalid API response: ' . json_last_error_msg(),
                $httpCode
            );
        }

        // API error
        if ($httpCode >= 400) {
            $message = $decoded['message']
                ?? $decoded['error']
                ?? 'API error (HTTP ' . $httpCode . ')';

            throw new SnippeException($message, $httpCode, $decoded ?? []);
        }

        return $decoded;
    }
}
