<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect\Contracts;

use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Data\TransactionResponse;
use Illuminate\Support\Collection;

interface GatewayInterface
{
    public function createTransaction(CreateTransactionRequest $request): TransactionResponse;

    public function getTransaction(string $id): TransactionResponse;

    public function listTransactions(array $filters = []): Collection;

    public function verifyWebhook(array $payload, string $signature): bool;
}
