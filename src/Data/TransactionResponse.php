<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Data;

class TransactionResponse
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $currency,
        public PaymentStatus $status,
        public ?string $url = null,
        public ?string $signature = null,
        public array $rawPayload = [],
    ) {
    }

    public static function fromBml(array $data): self
    {
        return new self(
            id: $data['id'] ?? $data['reference'] ?? '',
            amount: (int) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'MVR',
            status: PaymentStatus::fromBml($data['status'] ?? ''),
            url: $data['url'] ?? null,
            signature: $data['signature'] ?? null,
            rawPayload: $data,
        );
    }
}
