<?php

use Hadhiya\BmlConnect\Exceptions\SignatureMismatchException;

test('it can be instantiated', function () {
    $exception = new SignatureMismatchException('Invalid signature');

    expect($exception->getMessage())->toBe('Invalid signature')
        ->and($exception->getCode())->toBe(0);
});
