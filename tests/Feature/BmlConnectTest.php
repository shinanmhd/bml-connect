<?php

use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Data\PaymentStatus;
use Hadhiya\BmlConnect\Exceptions\BmlException;
use Hadhiya\BmlConnect\Facades\BmlConnect;
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
