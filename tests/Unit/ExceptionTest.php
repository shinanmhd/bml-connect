<?php

use IgniteLabs\BmlConnect\Exceptions\BmlException;
use IgniteLabs\BmlConnect\Exceptions\SignatureMismatchException;

test('it can be instantiated', function () {
    $exception = new SignatureMismatchException('Invalid signature');

    expect($exception->getMessage())->toBe('Invalid signature')
        ->and($exception->getCode())->toBe(0);
});

test('SignatureMismatchException::make() produces the canonical message', function () {
    $e = SignatureMismatchException::make();

    expect($e->getMessage())
        ->toBe('The signature provided by BML does not match the expected signature.');
});

test('SignatureMismatchException is an instance of BmlException', function () {
    expect(SignatureMismatchException::make())->toBeInstanceOf(BmlException::class);
});

test('BmlException exposes the HTTP status as its code', function () {
    $e = new BmlException('BML API request failed with HTTP 401.', 401);

    expect($e->getCode())->toBe(401);
});

test('BmlException with a 500 code reports correctly', function () {
    $e = new BmlException('BML API request failed with HTTP 500.', 500);

    expect($e->getCode())->toBe(500);
});

test('BmlException message does not expose sensitive data', function () {
    $e = new BmlException('BML API request failed with HTTP 422.', 422);

    expect($e->getMessage())->toContain('422')
        ->and($e->getMessage())->not->toContain('apiKey')
        ->and($e->getMessage())->not->toContain('secret');
});
