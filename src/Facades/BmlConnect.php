<?php

declare(strict_types=1);

namespace IgniteLabs\BmlConnect\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \IgniteLabs\BmlConnect\BmlConnect
 */
class BmlConnect extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \IgniteLabs\BmlConnect\BmlConnect::class;
    }
}
