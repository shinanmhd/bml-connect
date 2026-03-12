<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Exceptions;

class SignatureMismatchException extends BmlException
{
    public static function make(): self
    {
        return new self('The signature provided by BML does not match the expected signature.');
    }
}
