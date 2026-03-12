<?php

use Hadhiya\BmlConnect\BmlConnect;
use Hadhiya\BmlConnect\Facades\BmlConnect as BmlConnectFacade;

test('it registers the service provider and merges config', function () {
    expect(config('bml-connect'))->not->toBeNull()
        ->and(config('bml-connect.mode'))->toBe('sandbox');
});

test('it binds BmlConnect as a singleton', function () {
    $instance1 = app(BmlConnect::class);
    $instance2 = app(BmlConnect::class);

    expect($instance1)->toBeInstanceOf(BmlConnect::class)
        ->and($instance1)->toBe($instance2);
});

test('it can resolve via facade', function () {
    expect(BmlConnectFacade::getFacadeRoot())->toBeInstanceOf(BmlConnect::class);
});

test('it registers the alias', function () {
    expect(app('bml-connect'))->toBeInstanceOf(BmlConnect::class);
});
