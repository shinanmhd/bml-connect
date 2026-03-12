<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hadhiya\BmlConnect\BmlConnect
 */
class BmlConnect extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hadhiya\BmlConnect\BmlConnect::class;
    }
}
