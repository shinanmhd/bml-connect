<?php

use IgniteLabs\BmlConnect\Data\CreateTransactionRequest;

test('it validates the transaction amount', function () {
    expect(fn () => new CreateTransactionRequest(amount: 0))
        ->toThrow(InvalidArgumentException::class, 'Transaction amount must be a positive integer');

    expect(fn () => new CreateTransactionRequest(amount: -100))
        ->toThrow(InvalidArgumentException::class, 'Transaction amount must be a positive integer');
});

test('it validates the currency code', function () {
    expect(fn () => new CreateTransactionRequest(amount: 1000, currency: ''))
        ->toThrow(InvalidArgumentException::class, 'Currency code must not be empty');

    expect(fn () => new CreateTransactionRequest(amount: 1000, currency: '  '))
        ->toThrow(InvalidArgumentException::class, 'Currency code must not be empty');
});

test('it validates the redirect url', function () {
    expect(fn () => new CreateTransactionRequest(amount: 1000, redirectUrl: 'not-a-url'))
        ->toThrow(InvalidArgumentException::class, 'is not a valid URL');
});

test('it can be converted to an array', function () {
    $request = new CreateTransactionRequest(
        amount: 1000,
        currency: 'MVR',
        redirectUrl: 'https://test.com',
        localId: '123'
    );

    $array = $request->toArray();

    expect($array)->toBe([
        'amount' => 1000,
        'currency' => 'MVR',
        'redirectUrl' => 'https://test.com',
        'localId' => '123',
    ]);
});
