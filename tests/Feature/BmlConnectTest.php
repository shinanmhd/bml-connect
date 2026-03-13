<?php

use IgniteLabs\BmlConnect\Data\CreateTransactionRequest;
use IgniteLabs\BmlConnect\Data\PaymentStatus;
use IgniteLabs\BmlConnect\Exceptions\BmlException;
use IgniteLabs\BmlConnect\Facades\BmlConnect;
use Illuminate\Support\Facades\Http;

test('it can create a transaction', function () {
    Http::fake([
        '*/transactions' => Http::response([
            'id' => 'bml-123',
            'amount' => 1000,
            'currency' => 'MVR',
            'status' => 'READY',
            'url' => 'https://payments.bml.com.mv/pay/123',
            'signature' => 'mock-signature',
        ], 200),
    ]);

    $request = new CreateTransactionRequest(
        amount: 1000,
        currency: 'MVR',
        redirectUrl: 'https://example.com/callback',
        localId: 'order-789',
    );

    $response = BmlConnect::createTransaction($request);

    expect($response->id)->toBe('bml-123')
        ->and($response->status)->toBe(PaymentStatus::INITIATED)
        ->and($response->url)->toBe('https://payments.bml.com.mv/pay/123');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/transactions')
            && $request['amount'] === 1000
            && $request['currency'] === 'MVR'
            && isset($request['signature'])
            && isset($request['appId'])
            && $request['signMethod'] === 'sha1'
            && $request['apiVersion'] === '2.0';
    });
});

test('it can retrieve a transaction', function () {
    Http::fake([
        '*/transactions/bml-123' => Http::response([
            'id' => 'bml-123',
            'amount' => 1000,
            'currency' => 'MVR',
            'status' => 'SUCCESS',
            'url' => 'https://payments.bml.com.mv/pay/123',
            'signature' => 'mock-signature',
        ], 200),
    ]);

    $response = BmlConnect::getTransaction('bml-123');

    expect($response->id)->toBe('bml-123')
        ->and($response->status)->toBe(PaymentStatus::SUCCEEDED);
});

test('it can list transactions', function () {
    Http::fake([
        '*/transactions' => Http::response([
            'data' => [
                ['id' => 'bml-1', 'amount' => 100, 'currency' => 'MVR', 'status' => 'SUCCESS', 'url' => '', 'signature' => ''],
                ['id' => 'bml-2', 'amount' => 200, 'currency' => 'MVR', 'status' => 'FAIL', 'url' => '', 'signature' => ''],
            ],
        ], 200),
    ]);

    $transactions = BmlConnect::listTransactions();

    expect($transactions)->toHaveCount(2)
        ->and($transactions->first()->id)->toBe('bml-1')
        ->and($transactions->last()->status)->toBe(PaymentStatus::FAILED);
});

test('it throws BmlException when the api returns an error', function () {
    Http::fake([
        '*/transactions' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => BmlConnect::createTransaction(new CreateTransactionRequest(amount: 500)))
        ->toThrow(BmlException::class);
});

test('it does not expose response body in exception messages', function () {
    Http::fake([
        '*/transactions' => Http::response(['secret' => 'sensitive-data', 'apiKey' => 'leaked-key'], 500),
    ]);

    try {
        BmlConnect::createTransaction(new CreateTransactionRequest(amount: 500));
    } catch (BmlException $e) {
        expect($e->getMessage())
            ->not->toContain('sensitive-data')
            ->not->toContain('leaked-key')
            ->not->toContain('apiKey')
            ->toContain('500');
    }
});

test('it can verify a valid webhook signature', function () {
    $amount = 1000;
    $currency = 'MVR';
    $signature = sha1("amount={$amount}&currency={$currency}&apiKey=test-api-key");

    $payload = [
        'id' => 'bml-123',
        'amount' => $amount,
        'currency' => $currency,
        'status' => 'SUCCESS',
    ];

    expect(BmlConnect::verifyWebhook($payload, $signature))->toBeTrue();
});

test('it rejects a webhook with a tampered amount', function () {
    $signature = sha1('amount=1000&currency=MVR&apiKey=test-api-key');

    $payload = [
        'id' => 'bml-123',
        'amount' => 9999, // tampered
        'currency' => 'MVR',
        'status' => 'SUCCESS',
    ];

    expect(BmlConnect::verifyWebhook($payload, $signature))->toBeFalse();
});

test('it rejects a webhook with a tampered currency', function () {
    $signature = sha1('amount=1000&currency=MVR&apiKey=test-api-key');

    $payload = [
        'id' => 'bml-123',
        'amount' => 1000,
        'currency' => 'USD', // tampered
        'status' => 'SUCCESS',
    ];

    expect(BmlConnect::verifyWebhook($payload, $signature))->toBeFalse();
});

test('it rejects a webhook with a missing amount field', function () {
    $signature = sha1('amount=1000&currency=MVR&apiKey=test-api-key');

    $payload = ['currency' => 'MVR', 'status' => 'SUCCESS'];

    expect(BmlConnect::verifyWebhook($payload, $signature))->toBeFalse();
});

test('it rejects a webhook with a missing currency field', function () {
    $signature = sha1('amount=1000&currency=MVR&apiKey=test-api-key');

    $payload = ['amount' => 1000, 'status' => 'SUCCESS'];

    expect(BmlConnect::verifyWebhook($payload, $signature))->toBeFalse();
});

test('it rejects a webhook with an empty signature', function () {
    $payload = ['amount' => 1000, 'currency' => 'MVR', 'status' => 'SUCCESS'];

    expect(BmlConnect::verifyWebhook($payload, ''))->toBeFalse();
});

test('createTransaction sends a POST with correct headers and body fields', function () {
    Http::fake([
        '*/transactions' => Http::response([
            'id' => 'x', 'amount' => 500, 'currency' => 'MVR', 'status' => 'READY',
        ], 200),
    ]);

    BmlConnect::createTransaction(new CreateTransactionRequest(amount: 500));

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'test-api-key')
            && $request->hasHeader('Accept', 'application/json')
            && isset($request['signature'])
            && isset($request['appId'])
            && $request['signMethod'] === 'sha1'
            && $request['apiVersion'] === '2.0';
    });
});

test('createTransaction does not retry on failure', function () {
    Http::fake(['*/transactions' => Http::response([], 500)]);

    try {
        BmlConnect::createTransaction(new CreateTransactionRequest(amount: 500));
    } catch (BmlException) {
    }

    // Exactly one request — no auto-retry on POST (would cause duplicate charges)
    Http::assertSentCount(1);
});

test('getTransaction sends a GET request', function () {
    Http::fake([
        '*/transactions/txn-1' => Http::response([
            'id' => 'txn-1', 'amount' => 500, 'currency' => 'MVR', 'status' => 'SUCCESS',
        ], 200),
    ]);

    BmlConnect::getTransaction('txn-1');

    Http::assertSent(fn ($r) => $r->method() === 'GET');
});

test('getTransaction throws BmlException on 404', function () {
    Http::fake(['*/transactions/missing' => Http::response([], 404)]);

    expect(fn () => BmlConnect::getTransaction('missing'))
        ->toThrow(BmlException::class);
});

test('BmlException code matches the HTTP status returned by BML', function () {
    Http::fake(['*/transactions' => Http::response([], 422)]);

    try {
        BmlConnect::createTransaction(new CreateTransactionRequest(amount: 500));
    } catch (BmlException $e) {
        expect($e->getCode())->toBe(422);
    }
});

test('listTransactions maps a flat array response without a data wrapper', function () {
    Http::fake([
        '*/transactions' => Http::response([
            ['id' => 'flat-1', 'amount' => 100, 'currency' => 'MVR', 'status' => 'SUCCESS'],
            ['id' => 'flat-2', 'amount' => 200, 'currency' => 'MVR', 'status' => 'FAIL'],
        ], 200),
    ]);

    $transactions = BmlConnect::listTransactions();

    expect($transactions)->toHaveCount(2)
        ->and($transactions->first()->id)->toBe('flat-1');
});

test('listTransactions forwards filters as query parameters', function () {
    Http::fake(['*/transactions*' => Http::response(['data' => []], 200)]);

    BmlConnect::listTransactions(['status' => 'SUCCESS', 'page' => 2]);

    Http::assertSent(
        fn ($r) => str_contains($r->url(), 'status=SUCCESS')
            && str_contains($r->url(), 'page=2')
    );
});

test('createTransaction throws BmlException when BML returns non-JSON 200', function () {
    Http::fake([
        '*/transactions' => Http::response('OK', 200),
    ]);

    expect(fn () => BmlConnect::createTransaction(new CreateTransactionRequest(amount: 500)))
        ->toThrow(BmlException::class);
});
