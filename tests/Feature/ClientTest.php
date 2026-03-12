<?php

use Hadhiya\BmlConnect\BmlConnect;
use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Facades\BmlConnect as BmlConnectFacade;
use Hadhiya\BmlConnect\Http\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

test('it uses the sandbox endpoint when mode is sandbox', function () {
    Http::fake([
        '*uat.merchants*' => Http::response([
            'id' => 'x', 'amount' => 100, 'currency' => 'MVR', 'status' => 'READY',
        ], 200),
    ]);

    config()->set('bml-connect.mode', 'sandbox');

    $gateway = new BmlConnect(config('bml-connect'));
    $gateway->createTransaction(new CreateTransactionRequest(amount: 100));

    Http::assertSent(fn ($r) => str_contains($r->url(), 'uat.merchants.bankofmaldives.com.mv'));
});

test('it uses the production endpoint when mode is production', function () {
    Http::fake([
        '*api.merchants*' => Http::response([
            'id' => 'x', 'amount' => 100, 'currency' => 'MVR', 'status' => 'READY',
        ], 200),
    ]);

    config()->set('bml-connect.mode', 'production');

    $gateway = new BmlConnect(config('bml-connect'));
    $gateway->createTransaction(new CreateTransactionRequest(amount: 100));

    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.merchants.bankofmaldives.com.mv')
        && ! str_contains($r->url(), 'uat'));
});

test('the Authorization header sends the raw api key without a Bearer prefix', function () {
    Http::fake([
        '*/transactions' => Http::response([
            'id' => 'x', 'amount' => 100, 'currency' => 'MVR', 'status' => 'READY',
        ], 200),
    ]);

    BmlConnectFacade::createTransaction(new CreateTransactionRequest(amount: 100));

    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'test-api-key')
        && ! str_starts_with($r->header('Authorization')[0] ?? '', 'Bearer'));
});

test('the SSL verify option is enforced via reflection', function () {
    $client = new Client(config('bml-connect'));

    $ref = new ReflectionMethod($client, 'newRequest');
    $ref->setAccessible(true);
    /** @var PendingRequest $pendingRequest */
    $pendingRequest = $ref->invoke($client);

    // Read the internal $options property via reflection (no external packages needed)
    $optionsProp = new ReflectionProperty($pendingRequest, 'options');
    $optionsProp->setAccessible(true);
    $options = $optionsProp->getValue($pendingRequest);

    expect($options['verify'] ?? null)->toBeTrue();
});
