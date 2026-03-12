<?php

use Hadhiya\BmlConnect\Support\Signer;

test('it can generate a signature', function () {
    $signer = new Signer('test-api-key');
    $signature = $signer->sign(1000, 'MVR');

    // Expected: sha1('amount=1000&currency=MVR&apiKey=test-api-key')
    $expected = sha1('amount=1000&currency=MVR&apiKey=test-api-key');

    expect($signature)->toBe($expected);
});

test('it can verify a signature', function () {
    $signer = new Signer('test-api-key');
    $amount = 1000;
    $currency = 'MVR';
    $signature = sha1("amount={$amount}&currency={$currency}&apiKey=test-api-key");

    $payload = [
        'amount' => $amount,
        'currency' => $currency,
    ];

    expect($signer->verify($payload, $signature))->toBeTrue();
});

test('it fails verification for invalid amount', function () {
    $signer = new Signer('test-api-key');
    $signature = $signer->sign(1000, 'MVR');

    expect($signer->verify(['amount' => 2000, 'currency' => 'MVR'], $signature))->toBeFalse();
});
