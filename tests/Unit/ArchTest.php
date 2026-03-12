<?php

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
