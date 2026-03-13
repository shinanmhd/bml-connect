<?php

use IgniteLabs\BmlConnect\BmlConnect;
use IgniteLabs\BmlConnect\Contracts\GatewayInterface;
use IgniteLabs\BmlConnect\Exceptions\BmlException;
use IgniteLabs\BmlConnect\Exceptions\SignatureMismatchException;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

it('uses strict types')
    ->expect('IgniteLabs\BmlConnect')
    ->toUseStrictTypes();

it('ensures all files are in the correct namespace')
    ->expect('IgniteLabs\BmlConnect')
    ->toOnlyUse(['IgniteLabs\BmlConnect', 'Illuminate', 'GuzzleHttp', 'Psr'])
    ->ignoring(['config_path', 'config', 'app', 'collect']);

it('BmlConnect implements GatewayInterface', function () {
    expect(BmlConnect::class)
        ->toImplement(GatewayInterface::class);
});

it('SignatureMismatchException extends BmlException', function () {
    expect(SignatureMismatchException::class)
        ->toExtend(BmlException::class);
});
