<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Support;

class Signer
{
    public function __construct(private string $apiKey)
    {
    }

    /**
     * Generate a signature for the given transaction details.
     *
     * BML Connect defines the signature algorithm as:
     *   sha1("amount={amount}&currency={currency}&apiKey={apiKey}")
     *
     * SHA-1 is used here because it is the only signing algorithm supported
     * by the BML Connect API protocol. This is a BML API requirement, not a
     * design choice. See: https://bankofmaldives.stoplight.io/
     *
     * Note: The signature covers only amount and currency. The transaction ID
     * and status are NOT part of the signed payload (BML API limitation).
     * Applications should independently verify the transaction ID via a
     * back-channel `getTransaction` call after receiving a callback.
     */
    public function sign(int $amount, string $currency): string
    {
        $payload = "amount={$amount}&currency={$currency}&apiKey={$this->apiKey}";

        return sha1($payload);
    }

    /**
     * Verify the signature of an incoming webhook payload.
     *
     * Uses hash_equals() to perform a constant-time comparison, preventing
     * timing-based side-channel attacks.
     */
    public function verify(array $data, string $signature): bool
    {
        if (! isset($data['amount'], $data['currency'])) {
            return false;
        }

        return hash_equals($this->sign((int) $data['amount'], $data['currency']), $signature);
    }
}
