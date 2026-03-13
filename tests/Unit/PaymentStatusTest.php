<?php

use IgniteLabs\BmlConnect\Data\PaymentStatus;

test('it maps bml statuses correctly', function ($bmlStatus, $expected) {
    expect(PaymentStatus::fromBml($bmlStatus))->toBe($expected);
})->with([
    ['READY', PaymentStatus::INITIATED],
    ['PENDING', PaymentStatus::PENDING],
    ['SUCCESS', PaymentStatus::SUCCEEDED],
    ['FAIL', PaymentStatus::FAILED],
    ['CANCELLED', PaymentStatus::CANCELLED],
    ['EXPIRED', PaymentStatus::EXPIRED],
    ['UNKNOWN_VAL', PaymentStatus::FAILED],
]);

test('isSucceeded returns true only for SUCCEEDED', function () {
    expect(PaymentStatus::SUCCEEDED->isSucceeded())->toBeTrue()
        ->and(PaymentStatus::FAILED->isSucceeded())->toBeFalse()
        ->and(PaymentStatus::INITIATED->isSucceeded())->toBeFalse();
});

test('isFailed returns true only for FAILED', function () {
    expect(PaymentStatus::FAILED->isFailed())->toBeTrue()
        ->and(PaymentStatus::SUCCEEDED->isFailed())->toBeFalse();
});

test('isPending returns true only for PENDING', function () {
    expect(PaymentStatus::PENDING->isPending())->toBeTrue()
        ->and(PaymentStatus::SUCCEEDED->isPending())->toBeFalse();
});

test('isTerminal returns true for final states', function ($status, $expected) {
    expect($status->isTerminal())->toBe($expected);
})->with([
    [PaymentStatus::SUCCEEDED, true],
    [PaymentStatus::FAILED, true],
    [PaymentStatus::CANCELLED, true],
    [PaymentStatus::EXPIRED, true],
    [PaymentStatus::INITIATED, false],
    [PaymentStatus::PENDING, false],
]);

test('requiresPolling returns true for in-flight states', function ($status, $expected) {
    expect($status->requiresPolling())->toBe($expected);
})->with([
    [PaymentStatus::INITIATED, true],
    [PaymentStatus::PENDING, true],
    [PaymentStatus::SUCCEEDED, false],
    [PaymentStatus::FAILED, false],
    [PaymentStatus::CANCELLED, false],
    [PaymentStatus::EXPIRED, false],
]);
