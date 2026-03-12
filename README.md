# BML Connect Laravel Gateway Adapter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hadhiya/bml-connect.svg?style=flat-square)](https://packagist.org/packages/hadhiya/bml-connect)
[![Tests](https://img.shields.io/github/actions/workflow/status/hadhiya/bml-connect/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hadhiya/bml-connect/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/hadhiya/bml-connect.svg?style=flat-square)](https://packagist.org/packages/hadhiya/bml-connect)
[![License](https://img.shields.io/packagist/l/hadhiya/bml-connect.svg?style=flat-square)](https://packagist.org/packages/hadhiya/bml-connect)

A secure, testable Laravel gateway adapter for **Bank of Maldives (BML) Connect**. Handles request signing, HTTP communication, response normalisation, and webhook verification — so your application can focus on business logic.

---

## What This Package Does (and Doesn't Do)

| ✅ This package handles | ❌ This package does NOT handle |
|---|---|
| HTTP communication with the BML API | Storing transactions in your database |
| Signing requests with your API key | Queuing payment jobs |
| Normalising API responses into typed PHP objects | Sending email/SMS notifications |
| Verifying incoming webhook signatures | Routing, controllers, or middleware |
| Sandbox / production environment switching | Any UI or front-end |

> [!IMPORTANT]
> This is a **stateless adapter**. It is your application's responsibility to persist transaction records, track order status, and handle business logic. This package is the communication layer only.

---

## Architecture

```mermaid
flowchart LR
    subgraph your_app ["Your Laravel Application"]
        Controller["Controller\n(your code)"]
        Webhook["Webhook Handler\n(your code)"]
    end

    subgraph pkg ["hadhiya/bml-connect"]
        Facade["BmlConnect Facade"]
        Client["HTTP Client\n(signed requests)"]
        Signer["Request Signer\n(SHA-1 / BML spec)"]
        DTOs["Data Objects\nCreateTransactionRequest\nTransactionResponse\nPaymentStatus"]
    end

    BML["🏦 BML Connect API\n(sandbox / production)"]

    Controller -->|"createTransaction()\ngetTransaction()\nlistTransactions()"| Facade
    Webhook -->|"verifyWebhook()"| Facade
    Facade --> Client
    Facade --> Signer
    Client -->|"HTTPS"| BML
    BML -->|"JSON response"| Client
    Client --> DTOs
    DTOs --> Controller
```

---

## Payment Lifecycle

### ✅ Happy Path (Payment Succeeds)

```mermaid
sequenceDiagram
    actor User
    participant App as Your Laravel App
    participant BML as BML Connect API

    User->>App: POST /checkout (clicks Pay Now)
    App->>App: Create local pending order record
    App->>BML: createTransaction(amount, currency, redirectUrl)
    Note over App,BML: Package signs request with SHA-1 + API key
    BML-->>App: { id, url, status: READY }
    App->>App: Save BML transaction ID to your order record
    App-->>User: HTTP 302 → redirect to BML payment URL

    User->>BML: Enters card details
    BML->>BML: 3-D Secure verification
    BML-->>User: Redirect back to your redirectUrl?id=bml-txn-id

    User->>App: GET /payment/callback?id=bml-txn-id
    App->>BML: getTransaction(bml-txn-id)  ← back-channel verify
    BML-->>App: { status: SUCCESS }
    App->>App: Update order → PAID ✅
    App-->>User: Show success page
```

### ❌ Failure Path (Payment Declined or Expired)

```mermaid
sequenceDiagram
    actor User
    participant App as Your Laravel App
    participant BML as BML Connect API

    User->>BML: Enters card details
    BML->>BML: Bank declines / session expires

    alt Card declined
        BML-->>User: Redirect back → redirectUrl?id=bml-txn-id
        User->>App: GET /payment/callback?id=bml-txn-id
        App->>BML: getTransaction(bml-txn-id)
        BML-->>App: { status: FAIL }
        App->>App: Update order → FAILED ❌
        App-->>User: Show failure page + retry option
    else Session expired
        BML-->>User: Redirect back → redirectUrl?id=bml-txn-id
        User->>App: GET /payment/callback?id=bml-txn-id
        App->>BML: getTransaction(bml-txn-id)
        BML-->>App: { status: EXPIRED }
        App->>App: Update order → EXPIRED ⏱
        App-->>User: Show "session expired" page
    end
```

> [!WARNING]
> **Never trust the redirect alone.** A malicious user can visit your callback URL with any `?id=` value. Always call `getTransaction()` from your server (back-channel) to get the authoritative status from BML.

---

## Installation

**Requirements:** PHP 8.4+, Laravel 10 / 11 / 12

```bash
composer require hadhiya/bml-connect
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="bml-connect-config"
```

This creates `config/bml-connect.php` in your application.

---

## Configuration

Add the following to your `.env` file:

```env
BML_CONNECT_MODE=sandbox
BML_CONNECT_API_KEY=your-api-key-here
BML_CONNECT_APP_ID=your-app-id-here
```

| Variable | Description | Default | Required |
|---|---|---|---|
| `BML_CONNECT_MODE` | `sandbox` or `production` | `sandbox` | Yes |
| `BML_CONNECT_API_KEY` | Your BML Connect API key | — | Yes |
| `BML_CONNECT_APP_ID` | Your BML Connect application ID | — | Yes |

> [!CAUTION]
> **Never commit your API key to version control.** Always use environment variables. Rotate your key immediately if it is ever exposed.

The published config (`config/bml-connect.php`) also exposes timeout and retry settings:

```php
// config/bml-connect.php
return [
    'mode'    => env('BML_CONNECT_MODE', 'sandbox'),
    'api_key' => env('BML_CONNECT_API_KEY'),
    'app_id'  => env('BML_CONNECT_APP_ID'),

    'endpoints' => [
        'sandbox'    => 'https://api.uat.merchants.bankofmaldives.com.mv/public/',
        'production' => 'https://api.merchants.bankofmaldives.com.mv/public/',
    ],

    'timeout' => 30, // seconds — applies to all HTTP requests

    'retry' => [
        'times' => 3,   // only applies to GET (read) requests
        'sleep' => 100, // milliseconds between retries
    ],
];
```

> [!NOTE]
> Retry logic applies **only to read requests** (`getTransaction`, `listTransactions`). Transaction creation (`createTransaction`) never retries automatically — retrying a payment POST could cause duplicate charges.

---

## Step-by-Step Integration

### Step 1 — Create a Transaction

Use the `CreateTransactionRequest` DTO to build your request. The constructor validates your inputs immediately, before any network call is made.

```php
use Hadhiya\BmlConnect\Facades\BmlConnect;
use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Exceptions\BmlException;

$request = new CreateTransactionRequest(
    amount:      5000,                          // MVR 50.00 — smallest unit (laari)
    currency:    'MVR',
    redirectUrl: route('payment.callback'),     // where BML sends the user back
    localId:     'order-' . $order->id,         // your internal reference
);

try {
    $transaction = BmlConnect::createTransaction($request);
} catch (BmlException $e) {
    // BML API returned an HTTP error (4xx / 5xx)
    Log::error('BML transaction creation failed', ['code' => $e->getCode()]);
    return back()->withErrors('Payment unavailable. Please try again.');
}

// Save $transaction->id to your order before redirecting!
$order->update(['bml_transaction_id' => $transaction->id]);

return redirect($transaction->url);
```

**`CreateTransactionRequest` validation rules:**

| Parameter | Type | Rules | Notes |
|---|---|---|---|
| `amount` | `int` | Required, must be > 0 | Smallest currency unit (laari for MVR) |
| `currency` | `string` | Required, not empty | Default: `MVR` |
| `redirectUrl` | `?string` | Must be a valid URL if provided | Where BML redirects the user after payment |
| `localId` | `?string` | Optional | Your internal order/invoice ID |
| `provider` | `?string` | Optional | Specific payment provider (if required) |

> [!WARNING]
> If any validation rule is violated, an `\InvalidArgumentException` is thrown **before** any API call. See the [Exception Reference](#exception-reference) for details.

---

### Step 2 — Handle the Payment Callback

BML redirects the user back to your `redirectUrl` with a `?id=` query parameter containing the BML transaction ID. **Always verify the status server-side.**

```php
use Hadhiya\BmlConnect\Facades\BmlConnect;
use Hadhiya\BmlConnect\Exceptions\BmlException;
use Illuminate\Http\Request;

public function callback(Request $request)
{
    $bmlId = $request->query('id');

    if (! $bmlId) {
        return redirect()->route('home')->withErrors('Invalid callback.');
    }

    // Retrieve your order using the BML ID you saved in Step 1
    $order = Order::where('bml_transaction_id', $bmlId)->firstOrFail();

    try {
        $transaction = BmlConnect::getTransaction($bmlId);
    } catch (BmlException $e) {
        Log::error('BML status check failed', ['bml_id' => $bmlId, 'code' => $e->getCode()]);
        return view('payment.error');
    }

    // Use the enum helpers to branch your logic cleanly
    if ($transaction->status->isSucceeded()) {
        $order->update(['status' => 'paid']);
        return view('payment.success', compact('order'));
    }

    if ($transaction->status->isFailed()) {
        $order->update(['status' => 'failed']);
        return view('payment.failed', compact('order'));
    }

    if ($transaction->status->requiresPolling()) {
        // Still processing — show a pending page and poll or wait for webhook
        return view('payment.pending', compact('order'));
    }

    // CANCELLED or EXPIRED
    $order->update(['status' => $transaction->status->value]);
    return view('payment.cancelled', compact('order'));
}
```

---

### Step 3 — Handle Webhooks

BML can send asynchronous status updates to a webhook endpoint you register in the merchant portal. Always verify the signature before acting on the payload.

```mermaid
flowchart TD
    A([Incoming POST to /webhook/bml]) --> B{Signature\nvalid?}
    B -- ❌ No --> C[Return HTTP 403\nLog the attempt]
    B -- ✅ Yes --> D{Payment\nstatus?}
    D -- SUCCEEDED --> E[Mark order paid\nFulfil order]
    D -- FAILED --> F[Mark order failed\nNotify user]
    D -- CANCELLED --> G[Mark order cancelled]
    D -- EXPIRED --> H[Mark order expired]
    D -- Other --> I[Log unknown status\nDo not act]
    E & F & G & H & I --> J[Return HTTP 200]

    style C fill:#f66,color:#fff
    style J fill:#6c6,color:#fff
```

```php
use Hadhiya\BmlConnect\Facades\BmlConnect;
use Illuminate\Http\Request;

public function webhook(Request $request)
{
    $signature = $request->header('X-BML-Signature', '');

    // Always verify the signature first
    if (! BmlConnect::verifyWebhook($request->all(), $signature)) {
        Log::warning('BML webhook signature mismatch', [
            'ip' => $request->ip(),
        ]);
        return response('Forbidden', 403);
    }

    $payload = $request->all();
    $bmlId   = $payload['id'] ?? null;

    if (! $bmlId) {
        return response('Bad Request', 400);
    }

    $order = Order::where('bml_transaction_id', $bmlId)->first();

    if (! $order) {
        // Unknown transaction — return 200 so BML doesn't keep retrying
        return response('OK', 200);
    }

    $status = \Hadhiya\BmlConnect\Data\PaymentStatus::fromBml($payload['status'] ?? '');

    if ($status->isTerminal()) {
        $order->update(['status' => $status->value]);
        // Dispatch your application events here, e.g.:
        // OrderStatusUpdated::dispatch($order);
    }

    return response('OK', 200);
}
```

> [!IMPORTANT]
> Register your webhook URL in the BML Connect merchant portal. Return HTTP `200` for all verified requests — even for statuses you don't act on. If BML receives a non-200 response, it will retry delivery.

> [!NOTE]
> The webhook signature covers only `amount` and `currency` (BML API protocol constraint). Always cross-reference the `id` field against your own database to prevent replay attacks.

---

## Full Production Example

Below is a complete, self-contained `PaymentController` wiring all three steps together.

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Data\PaymentStatus;
use Hadhiya\BmlConnect\Exceptions\BmlException;
use Hadhiya\BmlConnect\Facades\BmlConnect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // ─── Step 1: Initiate ────────────────────────────────────────────────────

    public function initiate(Request $request)
    {
        $order = Order::findOrFail($request->order_id);

        // Prevent double-payment attempts
        if ($order->isPaid()) {
            return redirect()->route('orders.show', $order)->withErrors('Order is already paid.');
        }

        $transactionRequest = new CreateTransactionRequest(
            amount:      $order->total_in_laari,     // integer: 1 MVR = 100 laari
            currency:    'MVR',
            redirectUrl: route('payment.callback'),
            localId:     (string) $order->id,
        );

        try {
            $transaction = BmlConnect::createTransaction($transactionRequest);
        } catch (\InvalidArgumentException $e) {
            // Input validation failed (bad amount, invalid URL, etc.)
            Log::error('BML request validation failed', ['error' => $e->getMessage()]);
            return back()->withErrors('Invalid payment details. Please contact support.');
        } catch (BmlException $e) {
            // BML API returned an HTTP error
            Log::error('BML API error on create', ['code' => $e->getCode()]);
            return back()->withErrors('Payment gateway unavailable. Please try again.');
        }

        // Persist the BML transaction ID BEFORE redirecting
        $order->update([
            'bml_transaction_id' => $transaction->id,
            'payment_status'     => 'initiated',
        ]);

        return redirect($transaction->url);
    }

    // ─── Step 2: Callback ────────────────────────────────────────────────────

    public function callback(Request $request)
    {
        $bmlId = $request->query('id');

        if (! $bmlId) {
            return redirect()->route('home')->withErrors('Missing transaction ID.');
        }

        $order = Order::where('bml_transaction_id', $bmlId)->firstOrFail();

        // Already handled (e.g. by a webhook that arrived first)
        if ($order->payment_status === 'paid') {
            return view('payment.success', compact('order'));
        }

        try {
            $transaction = BmlConnect::getTransaction($bmlId);
        } catch (BmlException $e) {
            Log::error('BML status check failed on callback', ['bml_id' => $bmlId]);
            return view('payment.error');
        }

        $this->applyStatus($order, $transaction->status);

        return match (true) {
            $transaction->status->isSucceeded()     => view('payment.success', compact('order')),
            $transaction->status->isFailed()        => view('payment.failed', compact('order')),
            $transaction->status->requiresPolling() => view('payment.pending', compact('order')),
            default                                 => view('payment.cancelled', compact('order')),
        };
    }

    // ─── Step 3: Webhook ─────────────────────────────────────────────────────

    public function webhook(Request $request)
    {
        $signature = $request->header('X-BML-Signature', '');

        if (! BmlConnect::verifyWebhook($request->all(), $signature)) {
            Log::warning('BML webhook signature mismatch', ['ip' => $request->ip()]);
            return response('Forbidden', 403);
        }

        $bmlId = $request->input('id');
        $order = Order::where('bml_transaction_id', $bmlId)->first();

        if ($order) {
            $status = PaymentStatus::fromBml($request->input('status', ''));
            $this->applyStatus($order, $status);
        }

        // Always return 200 to prevent BML from retrying
        return response('OK', 200);
    }

    // ─── Shared ──────────────────────────────────────────────────────────────

    private function applyStatus(Order $order, PaymentStatus $status): void
    {
        if ($status->isTerminal() && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => $status->value]);

            // Dispatch application-level events here
            // event(new PaymentStatusChanged($order, $status));
        }
    }
}
```

**Routes (`routes/web.php`):**

```php
Route::middleware('web')->group(function () {
    Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
    Route::get('/payment/callback',  [PaymentController::class, 'callback'])->name('payment.callback');
});

// Webhook endpoint — exclude from CSRF verification
Route::post('/webhook/bml', [PaymentController::class, 'webhook'])->name('payment.webhook');
```

Add the webhook route to `VerifyCsrfToken` middleware exceptions:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'webhook/bml',
];
```

---

## Exception Reference

| Exception | Thrown by | When | `getCode()` |
|---|---|---|---|
| `\InvalidArgumentException` | `new CreateTransactionRequest(...)` | `amount ≤ 0`, empty `currency`, or invalid `redirectUrl` | `0` |
| `Hadhiya\BmlConnect\Exceptions\BmlException` | `createTransaction()`, `getTransaction()`, `listTransactions()` | BML API returns HTTP 4xx or 5xx | HTTP status (e.g. `401`, `500`) |
| `Hadhiya\BmlConnect\Exceptions\SignatureMismatchException` | (Provided for manual use) | Can be thrown manually when webhook verification fails and you want a typed exception | `0` |

> [!NOTE]
> `BmlException::getCode()` always returns the HTTP status code from BML. Use it to distinguish between authentication errors (`401`), validation errors (`422`), and server errors (`500`).

> [!WARNING]
> Exception messages from `BmlException` deliberately **do not include the API response body**. Raw API responses may contain sensitive payment data. Check your BML merchant portal logs for full error details.

**Catching exceptions — recommended pattern:**

```php
try {
    $transaction = BmlConnect::createTransaction($request);
} catch (\InvalidArgumentException $e) {
    // Input was invalid — this is a programmer error, log it
    Log::error('Invalid BML request', ['message' => $e->getMessage()]);
    return back()->withErrors('Invalid payment details.');
} catch (BmlException $e) {
    // Network or API-level error — retry may be appropriate
    Log::error('BML API error', ['code' => $e->getCode()]);
    return back()->withErrors('Payment gateway error. Please try again.');
}
```

---

## Payment Status Reference

### Status Mapping

| BML Raw Status | `PaymentStatus` Enum | Meaning |
|---|---|---|
| `READY` | `INITIATED` | Transaction created, waiting for the user to pay |
| `PENDING` | `PENDING` | User is on the BML payment page; bank is processing |
| `SUCCESS` | `SUCCEEDED` | Payment captured — safe to fulfil the order |
| `FAIL` | `FAILED` | Payment declined or errored |
| `CANCELLED` | `CANCELLED` | User abandoned the payment |
| `EXPIRED` | `EXPIRED` | BML session timed out |

### Status Lifecycle

```mermaid
stateDiagram-v2
    direction LR
    [*] --> INITIATED : createTransaction()
    INITIATED --> PENDING : User submits card
    PENDING --> SUCCEEDED : Bank approves
    PENDING --> FAILED : Bank declines
    INITIATED --> CANCELLED : User clicks cancel
    INITIATED --> EXPIRED : Session timeout

    SUCCEEDED --> [*]
    FAILED --> [*]
    CANCELLED --> [*]
    EXPIRED --> [*]
```

### Helper Methods on `PaymentStatus`

```php
$status = $transaction->status; // PaymentStatus enum

$status->isSucceeded();    // true only for SUCCEEDED
$status->isFailed();       // true only for FAILED
$status->isPending();      // true only for PENDING
$status->isTerminal();     // true for SUCCEEDED, FAILED, CANCELLED, EXPIRED
$status->requiresPolling();// true for INITIATED, PENDING (still in-flight)

// Compare directly with the enum case
if ($status === PaymentStatus::SUCCEEDED) { /* ... */ }

// Get the string value
echo $status->value; // e.g. "succeeded"
```

---

## Data Structures

### `CreateTransactionRequest`

```php
new CreateTransactionRequest(
    amount:      int,      // Required — value in smallest unit (laari for MVR)
    currency:    string,   // Default: 'MVR'
    redirectUrl: ?string,  // Optional — must be a valid URL if provided
    localId:     ?string,  // Optional — your internal order/invoice ID
    provider:    ?string,  // Optional — specific BML payment provider
);
```

### `TransactionResponse`

Returned by `createTransaction()` and `getTransaction()`.

| Property | Type | Description |
|---|---|---|
| `id` | `string` | BML's unique transaction ID |
| `amount` | `int` | Transaction amount (smallest unit) |
| `currency` | `string` | Currency code (e.g. `MVR`) |
| `status` | `PaymentStatus` | Normalised status enum |
| `url` | `?string` | BML-hosted payment page URL (only on creation) |
| `signature` | `?string` | BML's response signature |
| `rawPayload` | `array` | The complete, unmodified API response |

---

## Testing

This package uses [Pest PHP](https://pestphp.com). Run the suite:

```bash
composer test
```

### Writing Tests for Your Integration

Use Laravel's `Http::fake()` to mock BML API responses — no real API calls needed.

```php
use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Data\PaymentStatus;
use Hadhiya\BmlConnect\Exceptions\BmlException;
use Hadhiya\BmlConnect\Facades\BmlConnect;
use Illuminate\Support\Facades\Http;

// Test a successful transaction creation
test('payment initiation creates a BML transaction', function () {
    Http::fake([
        '*/transactions' => Http::response([
            'id'       => 'bml-test-123',
            'amount'   => 5000,
            'currency' => 'MVR',
            'status'   => 'READY',
            'url'      => 'https://payments.bankofmaldives.com.mv/pay/test-123',
        ], 200),
    ]);

    $response = BmlConnect::createTransaction(
        new CreateTransactionRequest(amount: 5000, currency: 'MVR')
    );

    expect($response->status)->toBe(PaymentStatus::INITIATED)
        ->and($response->url)->not->toBeNull();

    // Assert the request was signed correctly
    Http::assertSent(fn ($r) => isset($r['signature']) && isset($r['appId']));
});

// Test that API errors are surfaced as BmlException
test('payment initiation throws on API error', function () {
    Http::fake([
        '*/transactions' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => BmlConnect::createTransaction(
        new CreateTransactionRequest(amount: 5000)
    ))->toThrow(BmlException::class);
});

// Test webhook signature verification
test('webhook rejects tampered payloads', function () {
    $validSignature = sha1('amount=5000&currency=MVR&apiKey=' . config('bml-connect.api_key'));

    expect(BmlConnect::verifyWebhook(
        ['amount' => 9999, 'currency' => 'MVR', 'status' => 'SUCCESS'], // tampered amount
        $validSignature
    ))->toBeFalse();
});
```

### Testing in Sandbox

Set your `.env.testing` to use sandbox credentials:

```env
BML_CONNECT_MODE=sandbox
BML_CONNECT_API_KEY=your-sandbox-api-key
BML_CONNECT_APP_ID=your-sandbox-app-id
```

The sandbox environment (`api.uat.merchants.bankofmaldives.com.mv`) behaves identically to production. Test all status transitions before going live.

---

## Security Considerations

### 1. Back-Channel Verification Is Mandatory
Never trust the `?id=` parameter in the browser redirect to infer payment success. Always call `getTransaction()` from your server after the user lands on the callback URL. A user could manually construct a callback URL with any `?id=` value.

### 2. Webhook Signature Verification
All incoming webhook payloads must be verified with `verifyWebhook()` before being acted upon. The method uses `hash_equals()` for constant-time comparison, preventing timing-based attacks.

### 3. Protect Your API Key
- Store in `.env`, never in source code.
- Grant the minimum required permissions in the BML merchant portal.
- Rotate immediately if a key is ever committed to a repository or exposed in logs.

### 4. TLS Enforcement
All outgoing HTTP requests enforce TLS certificate verification (`verify: true`). Do not disable this, even in staging environments.

### 5. Idempotency
BML does not expose a native idempotency key header. Use the `localId` field to tie a BML transaction to your internal order ID. Before creating a new transaction, check whether your order already has a `bml_transaction_id` to prevent duplicate payment sessions.

### 6. Webhook Replay Attacks
The BML signature covers `amount` and `currency` only (BML API protocol limitation). After verifying the signature, always cross-check the `id` field against your database to confirm the transaction belongs to a known order.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## Security Disclosures

If you discover a security vulnerability, please email **shinaan.mv@gmail.com**. Do not open a GitHub issue for security matters.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.

