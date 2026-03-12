<?php

declare(strict_types=1);

namespace Hadhiya\BmlConnect;

use Hadhiya\BmlConnect\Contracts\GatewayInterface;
use Hadhiya\BmlConnect\Data\CreateTransactionRequest;
use Hadhiya\BmlConnect\Data\TransactionResponse;
use Hadhiya\BmlConnect\Http\Client;
use Hadhiya\BmlConnect\Support\Signer;
use Illuminate\Support\Collection;

class BmlConnect implements GatewayInterface
{
    protected Client $client;
    protected Signer $signer;

    public function __construct(protected array $config)
    {
        $this->client = new Client($config);
        $this->signer = new Signer($config['api_key'] ?? '');
    }

    public function createTransaction(CreateTransactionRequest $request): TransactionResponse
    {
        $data = $request->toArray();
        $data['signature'] = $this->signer->sign($request->amount, $request->currency);

        $response = $this->client->post('transactions', $data);

        return TransactionResponse::fromBml($response);
    }

    public function getTransaction(string $id): TransactionResponse
    {
        $response = $this->client->get("transactions/{$id}");

        return TransactionResponse::fromBml($response);
    }

    public function listTransactions(array $filters = []): Collection
    {
        $response = $this->client->get('transactions', $filters);

        return collect($response['data'] ?? $response)->map(function ($item) {
            return TransactionResponse::fromBml($item);
        });
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        return $this->signer->verify($payload, $signature);
    }
}
