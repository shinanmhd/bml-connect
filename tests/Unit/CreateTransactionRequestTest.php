<?php

use IgniteLabs\BmlConnect\Data\CreateTransactionRequest;

test('it creates a valid request with all fields', function () {
    $request = new CreateTransactionRequest(
        amount: 1000,
        currency: 'MVR',
        redirectUrl: 'https://example.com/callback',
        localId: 'order-123',
        provider: 'alia',
    );

    expect($request->amount)->toBe(1000)
        ->and($request->currency)->toBe('MVR')
        ->and($request->redirectUrl)->toBe('https://example.com/callback')
        ->and($request->localId)->toBe('order-123')
        ->and($request->provider)->toBe('alia');
});

test('it serialises correctly to array', function () {
    $request = new CreateTransactionRequest(
        amount: 500,
        currency: 'MVR',
        redirectUrl: 'https://example.com/pay',
        localId: 'ref-abc',
    );

    expect($request->toArray())->toBe([
        'amount' => 500,
        'currency' => 'MVR',
        'redirectUrl' => 'https://example.com/pay',
        'localId' => 'ref-abc',
    ]);
});

test('it omits null optional fields from array', function () {
    $request = new CreateTransactionRequest(amount: 200, currency: 'MVR');

    $array = $request->toArray();

    expect($array)->toHaveKey('amount')
        ->and($array)->toHaveKey('currency')
        ->and($array)->not->toHaveKey('redirectUrl')
        ->and($array)->not->toHaveKey('localId')
        ->and($array)->not->toHaveKey('provider');
});

test('it throws for zero amount', function () {
    expect(fn () => new CreateTransactionRequest(amount: 0))
        ->toThrow(InvalidArgumentException::class, 'positive integer');
});

test('it throws for negative amount', function () {
    expect(fn () => new CreateTransactionRequest(amount: -100))
        ->toThrow(InvalidArgumentException::class, 'positive integer');
});

test('it throws for empty currency', function () {
    expect(fn () => new CreateTransactionRequest(amount: 100, currency: ''))
        ->toThrow(InvalidArgumentException::class, 'Currency');
});

test('it throws for whitespace-only currency', function () {
    expect(fn () => new CreateTransactionRequest(amount: 100, currency: '   '))
        ->toThrow(InvalidArgumentException::class, 'Currency');
});

test('it throws for an invalid redirect url', function () {
    expect(fn () => new CreateTransactionRequest(
        amount: 100,
        currency: 'MVR',
        redirectUrl: 'not-a-valid-url'
    ))->toThrow(InvalidArgumentException::class, 'valid URL');
});

test('it accepts a valid https redirect url', function () {
    $request = new CreateTransactionRequest(
        amount: 100,
        currency: 'MVR',
        redirectUrl: 'https://mystore.mv/payment/callback',
    );

    expect($request->redirectUrl)->toBe('https://mystore.mv/payment/callback');
});

test('it accepts a null redirect url', function () {
    $request = new CreateTransactionRequest(amount: 100, currency: 'MVR', redirectUrl: null);

    expect($request->redirectUrl)->toBeNull();
});

test('toArray does not strip a localId of zero-string', function () {
    // Regression guard: bare array_filter() would silently drop '0' as falsy
    $request = new CreateTransactionRequest(amount: 100, currency: 'MVR', localId: '0');

    expect($request->toArray())->toHaveKey('localId', '0');
});

test('toArray does not strip a provider of zero-string', function () {
    $request = new CreateTransactionRequest(amount: 100, currency: 'MVR', provider: '0');

    expect($request->toArray())->toHaveKey('provider', '0');
});
