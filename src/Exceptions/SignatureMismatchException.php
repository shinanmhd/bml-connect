<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Exceptions;

/**
 * Thrown when a webhook signature does not match the expected value.
 *
 * This exception is provided as a typed helper for your application code.
 * The package itself returns `false` from `verifyWebhook()` rather than
 * throwing, giving you control over the response. If you prefer exception
 * semantics, throw it yourself after a failed verification:
 *
 * ```php
 * if (! BmlConnect::verifyWebhook($payload, $signature)) {
 *     throw SignatureMismatchException::make();
 * }
 * ```
 */
class SignatureMismatchException extends BmlException
{
    public static function make(): self
    {
        return new self('The signature provided by BML does not match the expected signature.');
    }
}
