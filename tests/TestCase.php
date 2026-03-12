<?php

namespace Hadhiya\BmlConnect\Tests;

use Hadhiya\BmlConnect\BmlConnectServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            BmlConnectServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('bml-connect.api_key', 'test-api-key');
        config()->set('bml-connect.app_id', 'test-app-id');
        config()->set('bml-connect.mode', 'sandbox');
    }
}
