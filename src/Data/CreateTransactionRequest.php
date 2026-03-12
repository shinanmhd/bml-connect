<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Data;

class CreateTransactionRequest
{
    public function __construct(
        public int $amount,
        public string $currency = 'MVR',
        public ?string $redirectUrl = null,
        public ?string $localId = null,
        public ?string $provider = null,
    ) {
        if ($this->amount <= 0) {
            throw new \InvalidArgumentException(
                'Transaction amount must be a positive integer representing the value in the smallest currency unit (e.g. laari for MVR).'
            );
        }

        if (trim($this->currency) === '') {
            throw new \InvalidArgumentException('Currency code must not be empty.');
        }

        if ($this->redirectUrl !== null && filter_var($this->redirectUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException(
                "The redirect URL \"{$this->redirectUrl}\" is not a valid URL."
            );
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'redirectUrl' => $this->redirectUrl,
            'localId' => $this->localId,
            'provider' => $this->provider,
        ]);
    }
}
