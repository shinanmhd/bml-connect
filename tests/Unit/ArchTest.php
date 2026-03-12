<?php

use Hadhiya\BmlConnect\BmlConnect;
use Hadhiya\BmlConnect\Contracts\GatewayInterface;
use Hadhiya\BmlConnect\Exceptions\BmlException;
use Hadhiya\BmlConnect\Exceptions\SignatureMismatchException;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

it('uses strict types')
    ->expect('Hadhiya\BmlConnect')
    ->toUseStrictTypes();

it('ensures all files are in the correct namespace')
    ->expect('Hadhiya\BmlConnect')
    ->toOnlyUse(['Hadhiya\BmlConnect', 'Illuminate', 'GuzzleHttp', 'Psr'])
    ->ignoring(['config_path', 'config', 'app', 'collect']);

it('BmlConnect implements GatewayInterface', function () {
    expect(BmlConnect::class)
        ->toImplement(GatewayInterface::class);
});

it('SignatureMismatchException extends BmlException', function () {
    expect(SignatureMismatchException::class)
        ->toExtend(BmlException::class);
});
