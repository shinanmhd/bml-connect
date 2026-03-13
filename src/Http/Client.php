<?php

declare(strict_types=1);

namespace IgniteLabs\BmlConnect\Http;

use IgniteLabs\BmlConnect\Exceptions\BmlException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    private const API_VERSION = '2.0';

    private const APP_VERSION = 'hadhiya-bml-connect-laravel';

    /**
     * BML Connect only supports SHA-1 as the signing method.
     * This is a BML API protocol requirement, not a design choice.
     * See: https://bankofmaldives.stoplight.io/
     */
    private const SIGN_METHOD = 'sha1';

    public function __construct(private array $config) {}

    /**
     * Perform a POST to the BML Connect API.
     *
     * Retries are intentionally disabled for POST requests. Automatically
     * retrying a payment-creation call can result in duplicate transactions
     * and double charges — a critical safety concern for any payment processor.
     */
    public function post(string $endpoint, array $data): array
    {
        $response = $this->newRequest()
            ->post($endpoint, array_merge($data, [
                'apiVersion' => self::API_VERSION,
                'appVersion' => self::APP_VERSION,
                'signMethod' => self::SIGN_METHOD,
                // appId is required by BML Connect for every transaction request.
                'appId' => $this->config['app_id'] ?? '',
            ]));

        return $this->handleResponse($response);
    }

    /**
     * Perform a GET against the BML Connect API.
     *
     * Read-only requests are safe to retry on transient network failures.
     * We pass throw: false so that failed responses are returned to
     * handleResponse() for conversion into our typed BmlException, rather
     * than being surfaced as an untyped RequestException by the retry layer.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->newRequest()
            ->retry(
                $this->config['retry']['times'] ?? 3,
                $this->config['retry']['sleep'] ?? 100,
                fn (\Exception $e) => ! ($e instanceof BmlException),
                throw: false,
            )
            ->get($endpoint, $query);

        return $this->handleResponse($response);
    }

    protected function newRequest(): PendingRequest
    {
        $baseUrl = $this->config['mode'] === 'production'
            ? $this->config['endpoints']['production']
            : $this->config['endpoints']['sandbox'];

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                /**
                 * BML Connect uses the raw API key as the Authorization value,
                 * without a scheme prefix such as "Bearer". This is a BML API
                 * protocol requirement (non-standard per RFC 7235).
                 */
                'Authorization' => $this->config['api_key'],
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout'] ?? 30)
            ->withOptions([
                // Explicitly enforce TLS certificate verification. Never disable
                // this in a payment integration, even in development.
                'verify' => true,
            ]);
    }

    protected function handleResponse(Response $response): array
    {
        if ($response->failed()) {
            // Do NOT include the response body in the exception message.
            // API error bodies may contain sensitive payment data that would
            // propagate into application logs and error trackers.
            throw new BmlException(
                "BML API request failed with HTTP {$response->status()}.",
                $response->status()
            );
        }

        $data = $response->json();

        // Response::json() returns null when the body is empty or non-JSON
        // (e.g. a proxy returning an HTML error page with a 200 status).
        // Propagate this as a clean BmlException rather than a TypeError.
        if (! is_array($data)) {
            throw new BmlException(
                'BML API returned an unexpected non-JSON response.',
                $response->status()
            );
        }

        return $data;
    }
}
