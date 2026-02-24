# Snippe PHP SDK

The simplest way to accept payments in Tanzania. Mobile Money, Cards, and QR codes — all in a few lines of PHP.

```php
$snippe = new Snippe('snp_your_api_key');

$payment = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->send();

echo $payment->reference(); // "9015c155-9e29-..."
echo $payment->status();    // "pending"
```

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Collecting Payments](#collecting-payments)
  - [Mobile Money](#mobile-money)
  - [Card Payments](#card-payments)
  - [Dynamic QR](#dynamic-qr)
- [Webhooks](#webhooks)
- [Payment Operations](#payment-operations)
  - [Find Payment](#find-payment)
  - [List Payments](#list-payments)
  - [Account Balance](#account-balance)
  - [Retry USSD Push](#retry-ussd-push)
  - [Search Payments](#search-payments)
- [Payment Object](#payment-object)
- [Error Handling](#error-handling)
- [Phone Number Normalization](#phone-number-normalization)
- [Configuration](#configuration)
- [Full Example: Bookstore](#full-example-bookstore)
- [API Reference](#api-reference)
- [Testing](#testing)

---

## Architecture

```mermaid
classDiagram
    class Snippe {
        -string apiKey
        -string baseUrl
        -string defaultWebhookUrl
        +mobileMoney(amount, phone) PaymentBuilder
        +card(amount) PaymentBuilder
        +qr(amount) PaymentBuilder
        +find(reference) Payment
        +payments(limit, offset) array
        +balance() array
        +push(reference, phone) array
        +search(reference) array
        +setWebhookUrl(url) self
        +setTimeout(seconds) self
    }

    class PaymentBuilder {
        -Snippe client
        -array payload
        +customer(name, email, lastName) self
        +phone(phone) self
        +billing(address, city, state, postcode, country) self
        +webhook(url) self
        +redirectTo(successUrl, cancelUrl) self
        +metadata(data) self
        +description(text) self
        +idempotencyKey(key) self
        +send() Payment
        +toArray() array
        +normalizePhone(phone)$ string
    }

    class Payment {
        -array data
        +reference() string
        +status() string
        +amount() int
        +currency() string
        +isPending() bool
        +isCompleted() bool
        +isFailed() bool
        +isExpired() bool
        +isVoided() bool
        +paymentUrl() string
        +qrCode() string
        +fees() int
        +netAmount() int
        +toArray() array
    }

    class Webhook {
        -array payload
        -array headers
        -string eventType
        +capture()$ Webhook
        +fromRaw(body, headers)$ Webhook
        +isPaymentCompleted() bool
        +isPaymentFailed() bool
        +reference() string
        +amount() int
        +customer() array
        +metadata() array
        +ok() void
        +fail(code) void
    }

    class SnippeException {
        -array response
        +getErrorCode() string
        +getResponse() array
    }

    Snippe --> PaymentBuilder : creates
    PaymentBuilder --> Payment : send returns
    Snippe --> Payment : find returns
    Webhook --|> SnippeException : throws
    PaymentBuilder --|> SnippeException : throws
    Snippe --|> SnippeException : throws
```

---

## Installation

```bash
composer require snippe/snippe-php
```

**Requirements:** PHP 8.1+, ext-curl, ext-json

---

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use Snippe\Snippe;

$snippe = new Snippe('snp_your_api_key');

// Collect TZS 5,000 via Airtel Money
$payment = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->webhook('https://yoursite.com/webhook')
    ->send();

echo $payment->reference(); // unique payment reference
echo $payment->status();    // "pending" — USSD push sent to phone
```

That's it. The customer gets a USSD prompt on their phone, enters their PIN, and you get a webhook when it's done.

### How It All Works

```mermaid
sequenceDiagram
    participant App as Your App
    participant SDK as Snippe SDK
    participant API as Snippe API
    participant Phone as Customer Phone

    App->>SDK: $snippe->mobileMoney(5000, phone)->send()
    SDK->>API: POST /v1/payments
    API-->>SDK: 201 Created { reference, status: pending }
    SDK-->>App: Payment object

    API->>Phone: USSD Push Notification
    Phone->>API: Customer enters PIN

    alt Payment Successful
        API->>App: POST /webhook (payment.completed)
        App->>SDK: Webhook::capture()
        SDK-->>App: isPaymentCompleted() = true
        App->>App: Update order to paid
        App-->>API: 200 OK
    else Payment Failed
        API->>App: POST /webhook (payment.failed)
        App->>SDK: Webhook::capture()
        SDK-->>App: isPaymentFailed() = true
        App->>App: Handle failure
        App-->>API: 200 OK
    end
```

---

## Collecting Payments

```mermaid
flowchart LR
    A["$snippe"] --> B["mobileMoney()"]
    A --> C["card()"]
    A --> D["qr()"]

    B --> E["USSD push to phone"]
    C --> F["Redirect to checkout page"]
    D --> G["Display QR code to scan"]

    E --> H["Webhook notification"]
    F --> H
    G --> H
```

### Mobile Money

Supported: **Airtel Money**, **M-Pesa**, **Mixx by Yas**, **Halotel** (Tanzania)

```php
$payment = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->send();
```

```mermaid
sequenceDiagram
    participant App as Your Server
    participant SDK as Snippe SDK
    participant API as Snippe API
    participant MNO as Mobile Network
    participant User as Customer Phone

    App->>SDK: mobileMoney(5000, "0754123456")
    SDK->>SDK: Normalize phone to 255754123456
    SDK->>API: POST /v1/payments { payment_type: mobile }
    API->>MNO: Request USSD push
    API-->>SDK: { reference, status: pending }
    SDK-->>App: Payment object

    MNO->>User: USSD prompt appears
    User->>MNO: Enters PIN
    MNO->>API: Payment confirmed

    API->>App: POST webhook { payment.completed }
    App->>SDK: Webhook::capture()
    App-->>API: 200 OK
```

The customer receives a USSD push notification. They enter their PIN to authorize. You get a `payment.completed` or `payment.failed` webhook.

**With all options:**

```php
$payment = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->webhook('https://yoursite.com/webhook')
    ->metadata(['order_id' => 'ORD-123'])
    ->description('Order from My Shop')
    ->idempotencyKey('order-123-attempt-1')
    ->send();
```

### Card Payments

Supported: **Visa**, **Mastercard**, local debit cards

```php
$payment = $snippe->card(10000)
    ->phone('0754123456')
    ->customer('John Doe', 'john@email.com')
    ->billing('123 Main Street', 'Dar es Salaam', 'DSM', '14101', 'TZ')
    ->redirectTo('https://yoursite.com/success', 'https://yoursite.com/cancel')
    ->send();

// Redirect the customer to the secure checkout page
header('Location: ' . $payment->paymentUrl());
```

```mermaid
sequenceDiagram
    participant App as Your Server
    participant SDK as Snippe SDK
    participant API as Snippe API
    participant Browser as Customer Browser
    participant Checkout as Secure Checkout

    App->>SDK: card(10000)->billing(...)->redirectTo(success, cancel)->send()
    SDK->>API: POST /v1/payments { payment_type: card }
    API-->>SDK: { reference, payment_url, status: pending }
    SDK-->>App: Payment object

    App->>Browser: Redirect to payment->paymentUrl()
    Browser->>Checkout: Customer enters card details
    Checkout->>API: Process card payment

    alt Success
        Checkout->>Browser: Redirect to success_url
        API->>App: POST webhook { payment.completed }
    else Cancelled
        Checkout->>Browser: Redirect to cancel_url
        API->>App: POST webhook { payment.failed }
    end
```

The customer is redirected to a secure checkout page, enters their card details, and is redirected back to your `redirect_url` or `cancel_url`.

### Dynamic QR

Generate a QR code that customers scan with their mobile money app.

```mermaid
sequenceDiagram
    participant App as Your Server
    participant SDK as Snippe SDK
    participant API as Snippe API
    participant Page as Your Checkout Page
    participant User as Customer Phone

    App->>SDK: qr(5000)->customer(...)->send()
    SDK->>API: POST /v1/payments { payment_type: dynamic-qr }
    API-->>SDK: { reference, payment_qr_code, payment_url }
    SDK-->>App: Payment object

    App->>Page: Render payment->qrCode() as QR image
    User->>Page: Scans QR with mobile money app
    User->>User: Confirms payment in app

    API->>App: POST webhook { payment.completed }
    App->>SDK: Webhook::capture()
    App-->>API: 200 OK
```

```php
$payment = $snippe->qr(5000)
    ->customer('John Doe', 'john@email.com')
    ->redirectTo('https://yoursite.com/success', 'https://yoursite.com/cancel')
    ->send();

// Render this as a QR image for the customer to scan
$qrData = $payment->qrCode();

// Or redirect to the hosted payment page
$paymentUrl = $payment->paymentUrl();
```

---

## Webhooks

When a payment completes or fails, Snippe sends a POST request to your webhook URL. The SDK makes handling it dead simple.

```mermaid
flowchart TD
    A["Snippe API sends POST to your webhook URL"] --> B["Webhook::capture()"]
    B --> C{"Parse JSON & normalize headers"}
    C -->|Valid| D{"Check event type"}
    C -->|Invalid JSON| E["Throw SnippeException"]

    D -->|payment.completed| F["isPaymentCompleted() = true"]
    D -->|payment.failed| G["isPaymentFailed() = true"]
    D -->|other| H["eventType() returns raw string"]

    F --> I["Get reference(), amount(), customer()"]
    G --> I
    H --> I

    I --> J["Update your database"]
    J --> K["$event->ok()  — respond 200"]
```

**webhook.php:**

```php
<?php
require 'vendor/autoload.php';

use Snippe\Webhook;

$event = Webhook::capture();

if ($event->isPaymentCompleted()) {
    $ref = $event->reference();
    // Mark order as paid in your database
    // $db->execute("UPDATE orders SET status = 'paid' WHERE payment_ref = ?", [$ref]);
}

if ($event->isPaymentFailed()) {
    $ref = $event->reference();
    // Handle failure
    // $db->execute("UPDATE orders SET status = 'failed' WHERE payment_ref = ?", [$ref]);
}

// Always respond 200 so Snippe knows you received it
$event->ok();
```

**What `Webhook::capture()` does for you:**
- Reads the raw POST body from `php://input`
- Parses JSON safely (throws `SnippeException` on invalid JSON)
- Normalizes headers to be **case-insensitive** (works on Apache, Nginx, PHP-FPM, etc.)
- Extracts the event type from the `X-Webhook-Event` header

### Webhook Data Accessors

```php
$event->eventType();    // "payment.completed" or "payment.failed"
$event->reference();    // payment reference string
$event->status();       // "completed", "failed", etc.
$event->amount();       // amount as integer (e.g. 5000)
$event->currency();     // "TZS"
$event->customer();     // ['first_name' => '...', 'last_name' => '...', 'email' => '...', 'phone' => '...']
$event->metadata();     // your custom metadata array
$event->payload();      // full raw payload as array
$event->rawBody();      // raw JSON string
```

### Webhook Responses

```php
$event->ok();           // respond 200 OK
$event->fail(400);      // respond with error code
```

### Testing Webhooks Locally

Use `Webhook::fromRaw()` to simulate webhook events in your tests:

```php
$body = json_encode([
    'data' => [
        'reference' => 'test-ref-123',
        'status'    => 'completed',
        'amount'    => ['value' => 5000, 'currency' => 'TZS'],
    ]
]);

$event = Webhook::fromRaw($body, [
    'X-Webhook-Event' => 'payment.completed',
]);

$event->isPaymentCompleted(); // true
$event->reference();          // "test-ref-123"
$event->amount();             // 5000
```

---

## Payment Operations

### Find Payment

Check the status of any payment by its reference.

```php
$payment = $snippe->find('9015c155-9e29-4e8e-8fe6-d5d81553c8e6');

echo $payment->status();     // "completed"
echo $payment->amount();     // 5000
echo $payment->currency();   // "TZS"
echo $payment->completedAt(); // "2026-01-25T00:50:44.105159Z"
```

### List Payments

Retrieve all your payments with pagination.

```php
$result = $snippe->payments(limit: 20, offset: 0);

$items = $result['data']['items'];  // array of payments
$total = $result['data']['total'];  // total count

foreach ($items as $item) {
    echo $item['reference'] . ' — ' . $item['status'] . "\n";
}
```

### Account Balance

```php
$balance = $snippe->balance();

$available = $balance['data']['available']['value'];    // e.g. 6943
$currency  = $balance['data']['available']['currency']; // "TZS"

echo "Balance: {$currency} " . number_format($available);
```

### Retry USSD Push

If the customer missed the USSD prompt or it timed out, trigger it again.

```php
// Retry to the original phone number
$snippe->push('payment-reference-id');

// Or send to a different phone number
$snippe->push('payment-reference-id', '+255787654321');
```

### Search Payments

Search for payments by reference. Returns the raw API response as an array. Unlike `find()` which returns a `Payment` object for a known reference, `search()` is useful for looking up payments when you have a partial reference or external reference.

```php
$result = $snippe->search('payment-reference');
```

---

## Payment Object

Every payment method returns a `Payment` object with these methods:

| Method | Returns | Description |
|--------|---------|-------------|
| `reference()` | `?string` | Unique payment reference |
| `status()` | `?string` | `pending`, `completed`, `failed`, `expired`, `voided` |
| `paymentType()` | `?string` | `mobile`, `card`, `dynamic-qr` |
| `amount()` | `?int` | Payment amount |
| `currency()` | `?string` | Currency code (`TZS`) |
| `isPending()` | `bool` | Is status pending? |
| `isCompleted()` | `bool` | Is status completed? |
| `isFailed()` | `bool` | Is status failed? |
| `isExpired()` | `bool` | Is status expired? |
| `isVoided()` | `bool` | Is status voided? |
| `paymentUrl()` | `?string` | Checkout URL (card/QR payments) |
| `qrCode()` | `?string` | QR code data string (QR payments) |
| `paymentToken()` | `?string` | Payment token |
| `fees()` | `?int` | Transaction fees (after completion) |
| `netAmount()` | `?int` | Net amount after fees |
| `expiresAt()` | `?string` | Expiration timestamp |
| `createdAt()` | `?string` | Creation timestamp |
| `completedAt()` | `?string` | Completion timestamp |
| `customer()` | `array` | Customer info |
| `toArray()` | `array` | Full response as array |

You can also access any field dynamically:

```php
$payment->api_version;  // "2026-01-25"
$payment->object;       // "payment"
```

---

## Error Handling

```mermaid
flowchart LR
    A["SDK makes API call"] --> B{"HTTP Status?"}
    B -->|200-201| C["Return Payment object"]
    B -->|400| D["SnippeException: validation_error"]
    B -->|401| E["SnippeException: unauthorized"]
    B -->|403| F["SnippeException: insufficient_scope"]
    B -->|404| G["SnippeException: not_found"]
    B -->|Network error| H["SnippeException: Network error"]

    D --> I["catch SnippeException"]
    E --> I
    F --> I
    G --> I
    H --> I

    I --> J["getMessage(), getCode(), getErrorCode(), getResponse()"]
```

All API errors throw `SnippeException` with the HTTP status code and full response.

```php
use Snippe\SnippeException;

try {
    $payment = $snippe->mobileMoney(5000, '0754123456')
        ->customer('John Doe', 'john@email.com')
        ->send();
} catch (SnippeException $e) {
    echo $e->getMessage();     // "invalid or missing API key"
    echo $e->getCode();        // 401
    echo $e->getErrorCode();   // "unauthorized"
    print_r($e->getResponse()); // full API error response
}
```

**Common errors:**

| HTTP Code | Error Code | Meaning |
|-----------|------------|---------|
| 400 | `validation_error` | Missing or invalid field |
| 401 | `unauthorized` | Bad or missing API key |
| 403 | `insufficient_scope` | API key lacks required scope |
| 404 | `not_found` | Payment not found |

---

## Phone Number Normalization

```mermaid
flowchart LR
    A["Input phone"] --> B["Strip non-digits"]
    B --> C{"Starts with +?"}
    C -->|Yes| D["Remove +"]
    C -->|No| E{"Starts with 0?"}

    D --> F{"Starts with 255?"}
    E -->|Yes| G["Replace 0 with 255"]
    E -->|No| F

    G --> H["255XXXXXXXXX"]
    F -->|Yes| H
    F -->|No| I{"9 digits?"}
    I -->|Yes| J["Prepend 255"]
    I -->|No| H
    J --> H
```

The SDK automatically normalizes Tanzanian phone numbers. All of these work:

```php
->phone('0754123456')       // local format
->phone('+255754123456')    // international with +
->phone('255754123456')     // international without +
->phone('754123456')        // no prefix
->phone('0754 123 456')    // with spaces
->phone('0754-123-456')    // with dashes
```

They all become `255754123456` in the API request.

---

## Configuration

### Default Webhook URL

Set once, applies to all payments:

```php
$snippe = new Snippe('snp_your_api_key');
$snippe->setWebhookUrl('https://yoursite.com/webhook');

// No need to call ->webhook() on every payment
$payment = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->send();
```

### Timeout

```php
$snippe->setTimeout(60); // seconds (default: 30)
```

### Custom Base URL

For testing against a mock server:

```php
$snippe->setBaseUrl('https://mock-api.yoursite.com/v1');
```

### Idempotency Keys

Prevent duplicate payments on retries. Auto-generated by default, or set your own:

```php
$payment = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->idempotencyKey('order-123-attempt-1')
    ->send();
```

Same key + same request body = returns cached response (valid for 24 hours).

### Preview Payload

Inspect what will be sent to the API without actually sending:

```php
$builder = $snippe->mobileMoney(5000, '0754123456')
    ->customer('John Doe', 'john@email.com')
    ->metadata(['order_id' => 'ORD-123']);

print_r($builder->toArray());

// Output:
// [
//     'payment_type' => 'mobile',
//     'details' => ['amount' => 5000, 'currency' => 'TZS'],
//     'phone_number' => '255754123456',
//     'customer' => ['firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@email.com'],
//     'metadata' => ['order_id' => 'ORD-123'],
// ]
```

---

## Full Example: Bookstore

A complete, working bookstore called **Duka la Vitabu** that accepts payments using the Snippe SDK. This was tested live against the real Snippe API.

```mermaid
sequenceDiagram
    actor Customer
    participant Shop as Bookstore UI
    participant Pay as pay.php
    participant SDK as Snippe SDK
    participant API as Snippe API
    participant WH as webhook.php
    participant DB as orders.json

    Customer->>Shop: Browse books & add to cart
    Customer->>Shop: Fill checkout form
    Shop->>Pay: POST name, email, phone, method, amount

    Pay->>SDK: mobileMoney(1000, phone)->customer(name, email)->send()
    SDK->>API: POST /v1/payments
    API-->>SDK: { reference, status: pending }
    SDK-->>Pay: Payment object

    Pay->>DB: Save order { reference, status: pending, books }
    Pay->>Shop: Redirect to success page

    Note over API,Customer: Customer gets USSD push on phone and enters PIN

    API->>WH: POST { payment.completed, reference }
    WH->>SDK: Webhook::capture()
    SDK-->>WH: isPaymentCompleted() = true
    WH->>DB: Update order status to completed
    WH-->>API: 200 OK

    Customer->>Shop: View Orders page
    Shop->>DB: Read orders
    DB-->>Shop: order.status = completed
    Shop-->>Customer: Order shows as paid
```

### Project Structure

```
bookstore/
├── composer.json
├── orders.json           ← auto-created, stores orders
├── webhook.log           ← auto-created, logs webhook events
└── public/
    ├── index.php         ← shop, cart, checkout, orders UI
    ├── pay.php           ← payment processing
    └── webhook.php       ← receives Snippe webhooks
```

### composer.json

```json
{
    "require": {
        "snippe/snippe-php": "*"
    }
}
```

### pay.php — Payment Processing

This is the checkout handler. The customer selects a book, fills in their info, and this file charges them using the Snippe SDK.

```php
<?php
session_start();
require 'vendor/autoload.php';

use Snippe\Snippe;
use Snippe\SnippeException;

$snippe = new Snippe('snp_your_api_key');
$snippe->setWebhookUrl('https://yoursite.com/webhook.php');

$name    = $_POST['full_name'];
$email   = $_POST['email'];
$phone   = $_POST['phone'];
$method  = $_POST['payment_method'];  // "mobile_airtel", "mobile_mpesa", "card"
$amount  = (int) $_POST['amount'];    // e.g. 1000 (TZS)

try {
    if (str_starts_with($method, 'mobile_')) {

        // ── Mobile Money ──
        $payment = $snippe->mobileMoney($amount, $phone)
            ->customer($name, $email)
            ->metadata(['order_id' => 'ORD-' . uniqid()])
            ->description('Book order from Duka la Vitabu')
            ->send();

        // USSD push sent to the customer's phone
        header('Location: success.php?ref=' . $payment->reference());

    } elseif ($method === 'card') {

        // ── Card Payment ──
        $payment = $snippe->card($amount)
            ->phone($phone)
            ->customer($name, $email)
            ->billing('N/A', 'Dar es Salaam', 'DSM', '14101', 'TZ')
            ->redirectTo('https://yoursite.com/success', 'https://yoursite.com/cancel')
            ->send();

        // Redirect customer to the secure checkout page
        header('Location: ' . $payment->paymentUrl());
    }

} catch (SnippeException $e) {
    // Show error to customer
    $_SESSION['error'] = 'Payment failed: ' . $e->getMessage();
    header('Location: checkout.php');
}
```

### webhook.php — Receive Payment Notifications

When the customer completes (or fails) the payment, Snippe sends a webhook. This is your entire webhook handler:

```php
<?php
require 'vendor/autoload.php';

use Snippe\Webhook;

$event = Webhook::capture();

if ($event->isPaymentCompleted()) {
    $ref = $event->reference();

    // Update your order in the database
    $db->execute(
        "UPDATE orders SET status = 'paid', paid_at = NOW() WHERE payment_ref = ?",
        [$ref]
    );
}

if ($event->isPaymentFailed()) {
    $ref = $event->reference();

    $db->execute(
        "UPDATE orders SET status = 'failed' WHERE payment_ref = ?",
        [$ref]
    );
}

// Always respond 200 so Snippe knows you received it
$event->ok();
```

### Live Test Results

We ran this bookstore against the real Snippe API:

```
═══════════════════════════════════════════
  Snippe PHP SDK — Live API Tests
═══════════════════════════════════════════

   Get Account Balance        — TZS 5,240
   List Payments              — 48 payments found
   Create Mobile Money        — ref: 083439ef-c4bb-4010-...
   Get Payment Status         — pending, TZS 500
   Webhook Parsing            — payment.completed handled
   Error Handling             — 401 caught correctly

  Results: 6 passed, 0 failed
═══════════════════════════════════════════
```

### Book Catalog

The test bookstore includes these titles at TZS 1,000 each:

| # | Title | Author |
|---|-------|--------|
| 1 | Things Fall Apart | Chinua Achebe |
| 2 | Half of a Yellow Sun | Chimamanda Ngozi Adichie |
| 3 | The Beautyful Ones Are Not Yet Born | Ayi Kwei Armah |
| 4 | Nervous Conditions | Tsitsi Dangarembga |
| 5 | Wizard of the Crow | Ngugi wa Thiong'o |
| 6 | Americanah | Chimamanda Ngozi Adichie |

---

## API Reference

### `Snippe` Class

| Method | Returns | Description |
|--------|---------|-------------|
| `new Snippe($apiKey)` | `Snippe` | Create client with your API key |
| `setWebhookUrl($url)` | `self` | Set default webhook URL for all payments |
| `setTimeout($seconds)` | `self` | Set request timeout (default 30s) |
| `setBaseUrl($url)` | `self` | Override API base URL |
| `mobileMoney($amount, $phone)` | `PaymentBuilder` | Start a mobile money payment |
| `card($amount)` | `PaymentBuilder` | Start a card payment |
| `qr($amount)` | `PaymentBuilder` | Start a QR code payment |
| `find($reference)` | `Payment` | Get payment by reference |
| `payments($limit, $offset)` | `array` | List all payments |
| `balance()` | `array` | Get account balance |
| `push($reference, $phone?)` | `array` | Retry USSD push |
| `search($reference)` | `array` | Search payments |
| `getWebhookUrl()` | `?string` | Get the configured default webhook URL |

### `PaymentBuilder` Class

| Method | Returns | Description |
|--------|---------|-------------|
| `type($type)` | `self` | Set payment type (`mobile`, `card`, `dynamic-qr`) |
| `customer($name, $email, $lastName?)` | `self` | Set customer (auto-splits full name) |
| `phone($phone)` | `self` | Set phone number (auto-normalizes) |
| `billing($address, $city, $state, $postcode, $country?)` | `self` | Billing address (cards) |
| `webhook($url)` | `self` | Set webhook URL |
| `redirectTo($successUrl, $cancelUrl)` | `self` | Redirect URLs (cards/QR) |
| `metadata($data)` | `self` | Custom metadata array |
| `description($text)` | `self` | Payment description |
| `idempotencyKey($key)` | `self` | Custom idempotency key |
| `amount($amount, $currency?)` | `self` | Set amount (default TZS) |
| `send()` | `Payment` | Execute the payment |
| `toArray()` | `array` | Preview the payload |

### `Webhook` Class

| Method | Returns | Description |
|--------|---------|-------------|
| `Webhook::capture()` | `Webhook` | Capture incoming webhook request |
| `Webhook::fromRaw($body, $headers)` | `Webhook` | Create from raw data (testing) |
| `eventType()` | `string` | Event type string |
| `isPaymentCompleted()` | `bool` | Is it a completed payment? |
| `isPaymentFailed()` | `bool` | Is it a failed payment? |
| `reference()` | `?string` | Payment reference |
| `status()` | `?string` | Payment status |
| `amount()` | `?int` | Payment amount |
| `currency()` | `?string` | Currency code |
| `customer()` | `array` | Customer data |
| `metadata()` | `array` | Custom metadata |
| `payload()` | `array` | Full payload |
| `rawBody()` | `string` | Raw JSON string |
| `ok()` | `void` | Respond 200 OK |
| `fail($code?)` | `void` | Respond with error |

### `SnippeException` Class

| Method | Returns | Description |
|--------|---------|-------------|
| `getMessage()` | `string` | Error message from API |
| `getCode()` | `int` | HTTP status code |
| `getErrorCode()` | `?string` | API error code (e.g. `unauthorized`) |
| `getResponse()` | `array` | Full error response from API |

---

## Testing

Run the SDK test suite:

```bash
composer install
./vendor/bin/phpunit
```

```
OK (46 tests, 112 assertions)
```

The test suite covers:
- Phone number normalization (9 formats)
- Payment builder payloads (mobile, card, QR)
- Customer name splitting
- Webhook event parsing (completed, failed, edge cases)
- Header case insensitivity
- Payment response object methods
- Error handling and exception data
- Client configuration

---

## Payment Status Flow

```mermaid
stateDiagram-v2
    [*] --> pending : Payment Created

    pending --> completed : Customer authorized payment
    pending --> failed : Declined / timeout / error
    pending --> expired : 4 hours without action
    pending --> voided : Cancelled before completion

    completed --> [*]
    failed --> [*]
    expired --> [*]
    voided --> [*]

    note right of pending
        USSD push sent to phone
        Waiting for customer PIN
    end note

    note right of completed
        Funds received
        Webhook: payment.completed
    end note

    note left of failed
        Payment declined
        Webhook: payment.failed
    end note
```

Payments expire after **4 hours** if not completed. Create a new payment if the customer wants to retry.

---

## License

MIT
