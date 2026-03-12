<?php

use Hadhiya\BmlConnect\Data\PaymentStatus;
use Hadhiya\BmlConnect\Data\TransactionResponse;

test('it maps all fields from a full bml response', function () {
    $response = TransactionResponse::fromBml([
        'id' => 'txn-001',
        'amount' => 1500,
        'currency' => 'USD',
        'status' => 'SUCCESS',
        'url' => 'https://pay.bml.com.mv/pay/001',
        'signature' => 'abc123',
    ]);

    expect($response->id)->toBe('txn-001')
        ->and($response->amount)->toBe(1500)
        ->and($response->currency)->toBe('USD')
        ->and($response->status)->toBe(PaymentStatus::SUCCEEDED)
        ->and($response->url)->toBe('https://pay.bml.com.mv/pay/001')
        ->and($response->signature)->toBe('abc123');
});

test('it falls back to reference key when id is absent', function () {
    $response = TransactionResponse::fromBml([
        'reference' => 'ref-xyz',
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'READY',
    ]);

    expect($response->id)->toBe('ref-xyz');
});

test('it returns empty string id when neither id nor reference is present', function () {
    $response = TransactionResponse::fromBml([
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'SUCCESS',
    ]);

    expect($response->id)->toBe('');
});

test('it defaults amount to 0 when missing', function () {
    $response = TransactionResponse::fromBml(['id' => 'x', 'currency' => 'MVR', 'status' => 'SUCCESS']);

    expect($response->amount)->toBe(0);
});

test('it defaults currency to MVR when missing', function () {
    $response = TransactionResponse::fromBml(['id' => 'x', 'amount' => 500, 'status' => 'SUCCESS']);

    expect($response->currency)->toBe('MVR');
});

test('it casts a string amount to integer', function () {
    $response = TransactionResponse::fromBml([
        'id' => 'x',
        'amount' => '750',
        'currency' => 'MVR',
        'status' => 'SUCCESS',
    ]);

    expect($response->amount)->toBe(750)->toBeInt();
});

test('it preserves the raw payload in full', function () {
    $data = [
        'id' => 'x',
        'amount' => 500,
        'currency' => 'MVR',
        'status' => 'SUCCESS',
        'extra_field' => 'some-bml-metadata',
    ];

    $response = TransactionResponse::fromBml($data);

    expect($response->rawPayload)->toBe($data);
});

test('it handles an entirely empty payload without throwing', function () {
    $response = TransactionResponse::fromBml([]);

    expect($response->amount)->toBe(0)
        ->and($response->currency)->toBe('MVR')
        ->and($response->id)->toBe('');
});

test('id takes precedence over reference when both are present', function () {
    $response = TransactionResponse::fromBml([
        'id' => 'id-wins',
        'reference' => 'ref-loses',
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'SUCCESS',
    ]);

    expect($response->id)->toBe('id-wins');
});

test('url defaults to null when absent', function () {
    $response = TransactionResponse::fromBml(['id' => 'x', 'amount' => 100, 'currency' => 'MVR', 'status' => 'SUCCESS']);

    expect($response->url)->toBeNull();
});

test('signature defaults to null when absent', function () {
    $response = TransactionResponse::fromBml(['id' => 'x', 'amount' => 100, 'currency' => 'MVR', 'status' => 'SUCCESS']);

    expect($response->signature)->toBeNull();
});
