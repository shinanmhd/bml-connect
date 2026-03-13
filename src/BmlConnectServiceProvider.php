<?php

declare(strict_types=1);

namespace IgniteLabs\BmlConnect;

use Illuminate\Support\ServiceProvider;

class BmlConnectServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/bml-connect.php' => config_path('bml-connect.php'),
            ], 'bml-connect-config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bml-connect.php', 'bml-connect');

        $this->app->singleton(BmlConnect::class, function ($app) {
            return new BmlConnect(config('bml-connect'));
        });

        $this->app->alias(BmlConnect::class, 'bml-connect');
    }
}
