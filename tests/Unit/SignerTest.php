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

test('it returns false when the currency field is missing from the payload', function () {
    $signer = new Signer('test-api-key');
    $signature = $signer->sign(1000, 'MVR');

    expect($signer->verify(['amount' => 1000], $signature))->toBeFalse();
});

test('it returns false when both amount and currency are missing', function () {
    $signer = new Signer('test-api-key');

    expect($signer->verify(['status' => 'SUCCESS'], 'any-signature'))->toBeFalse();
});

test('it returns false for an empty signature string', function () {
    $signer = new Signer('test-api-key');

    expect($signer->verify(['amount' => 1000, 'currency' => 'MVR'], ''))->toBeFalse();
});

test('it accepts a string amount via integer cast when verifying', function () {
    // BML webhooks may send numeric values as strings
    $signer = new Signer('test-api-key');
    $signature = $signer->sign(1000, 'MVR');

    expect($signer->verify(['amount' => '1000', 'currency' => 'MVR'], $signature))->toBeTrue();
});

test('different api keys produce different signatures', function () {
    $sig1 = (new Signer('key-a'))->sign(1000, 'MVR');
    $sig2 = (new Signer('key-b'))->sign(1000, 'MVR');

    expect($sig1)->not->toBe($sig2);
});

test('signatures are case-sensitive — uppercase signature fails verification', function () {
    $signer = new Signer('test-api-key');
    $upperSig = strtoupper($signer->sign(1000, 'MVR'));

    expect($signer->verify(['amount' => 1000, 'currency' => 'MVR'], $upperSig))->toBeFalse();
});
