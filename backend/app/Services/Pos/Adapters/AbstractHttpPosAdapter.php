<?php

namespace App\Services\Pos\Adapters;

use App\Contracts\Pos\PosConnectionResult;
use App\Contracts\Pos\PosOrderPayload;
use App\Contracts\Pos\PosOrderResult;
use App\Contracts\Pos\PosOrderStatusResult;
use App\Contracts\Pos\PosProductMapping;
use App\Contracts\Pos\PosProductSyncResult;
use App\Contracts\Pos\POSAdapterInterface;
use App\Models\Order;
use App\Models\PosIntegration;
use App\Support\LogRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class AbstractHttpPosAdapter implements POSAdapterInterface
{
    protected int $timeoutSeconds = 15;

    abstract protected function providerLabel(): string;

    abstract protected function authHeaders(PosIntegration $integration): array;

    abstract protected function ordersPath(PosIntegration $integration): string;

    public function authenticate(PosIntegration $integration): PosConnectionResult
    {
        return $this->testConnection($integration);
    }

    public function testConnection(PosIntegration $integration): PosConnectionResult
    {
        if ($this->usesStubMode($integration)) {
            return new PosConnectionResult(true, 'Stub mode: connection simulated.');
        }

        $endpoint = $this->baseUrl($integration);
        if ($endpoint === '') {
            return new PosConnectionResult(false, 'API endpoint is not configured.');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->authHeaders($integration))
                ->get(rtrim($endpoint, '/').'/health');

            if ($response->successful()) {
                return new PosConnectionResult(true, 'Connection successful.');
            }

            return new PosConnectionResult(
                false,
                $this->sanitizeMessage('Connection failed with HTTP '.$response->status())
            );
        } catch (ConnectionException) {
            return new PosConnectionResult(false, 'POS unreachable (timeout or network error).');
        } catch (RequestException $e) {
            return new PosConnectionResult(false, $this->sanitizeMessage($e->getMessage()));
        }
    }

    public function getProducts(PosIntegration $integration): array
    {
        if ($this->usesStubMode($integration)) {
            return [];
        }

        $endpoint = $this->baseUrl($integration);
        if ($endpoint === '') {
            return [];
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->authHeaders($integration))
                ->get(rtrim($endpoint, '/').'/products');

            if (! $response->successful()) {
                return [];
            }

            $items = $response->json('data') ?? $response->json() ?? [];

            return collect($items)
                ->filter(fn ($item) => is_array($item) && filled($item['id'] ?? $item['external_id'] ?? null))
                ->map(fn (array $item) => new \App\Contracts\Pos\PosExternalProduct(
                    externalId: (string) ($item['id'] ?? $item['external_id']),
                    name: (string) ($item['name'] ?? 'Product'),
                    sku: isset($item['sku']) ? (string) $item['sku'] : null,
                    metadata: is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
                ))
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function syncProduct(PosIntegration $integration, PosProductMapping $mapping): PosProductSyncResult
    {
        return new PosProductSyncResult(true, 'Product mapping stored locally.');
    }

    public function createOrder(PosIntegration $integration, Order $order, PosOrderPayload $payload): PosOrderResult
    {
        if ($this->usesStubMode($integration)) {
            return new PosOrderResult(
                success: true,
                externalOrderId: 'POS-'.Str::upper(Str::random(5)),
                message: 'Stub mode: order accepted.',
                metadata: ['stub' => true],
            );
        }

        $endpoint = $this->baseUrl($integration);
        if ($endpoint === '') {
            return new PosOrderResult(false, message: 'API endpoint is not configured.');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(array_merge($this->authHeaders($integration), [
                    'Idempotency-Key' => $payload->idempotencyKey,
                ]))
                ->post(rtrim($endpoint, '/').$this->ordersPath($integration), $this->serializeOrder($integration, $payload));

            if ($response->status() === 409) {
                $existingId = $response->json('external_order_id') ?? $response->json('order_id');

                if ($existingId) {
                    return new PosOrderResult(
                        success: true,
                        externalOrderId: (string) $existingId,
                        message: 'Order already exists (idempotent).',
                        metadata: $this->safeResponse($response->json()),
                    );
                }
            }

            if (! $response->successful()) {
                return new PosOrderResult(
                    false,
                    message: $this->sanitizeMessage($response->json('message') ?? 'POS rejected the order.'),
                    metadata: $this->safeResponse($response->json()),
                );
            }

            $externalId = $response->json('external_order_id')
                ?? $response->json('order_id')
                ?? $response->json('id');

            if (! $externalId) {
                return new PosOrderResult(false, message: 'POS response missing external order ID.');
            }

            return new PosOrderResult(
                success: true,
                externalOrderId: (string) $externalId,
                message: 'Order synchronized.',
                metadata: $this->safeResponse($response->json()),
            );
        } catch (ConnectionException) {
            return new PosOrderResult(false, message: 'POS unreachable (timeout or network error).');
        } catch (RequestException $e) {
            return new PosOrderResult(false, message: $this->sanitizeMessage($e->getMessage()));
        }
    }

    public function updateOrder(PosIntegration $integration, string $externalOrderId, PosOrderPayload $payload): PosOrderResult
    {
        if ($this->usesStubMode($integration)) {
            return new PosOrderResult(true, externalOrderId: $externalOrderId, message: 'Stub mode: order updated.');
        }

        $endpoint = $this->baseUrl($integration);
        if ($endpoint === '') {
            return new PosOrderResult(false, message: 'API endpoint is not configured.');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->authHeaders($integration))
                ->patch(rtrim($endpoint, '/').$this->ordersPath($integration).'/'.$externalOrderId, $this->serializeOrder($integration, $payload));

            if (! $response->successful()) {
                return new PosOrderResult(false, message: $this->sanitizeMessage($response->json('message') ?? 'POS update failed.'));
            }

            return new PosOrderResult(true, externalOrderId: $externalOrderId, metadata: $this->safeResponse($response->json()));
        } catch (ConnectionException) {
            return new PosOrderResult(false, message: 'POS unreachable (timeout or network error).');
        } catch (RequestException $e) {
            return new PosOrderResult(false, message: $this->sanitizeMessage($e->getMessage()));
        }
    }

    public function cancelOrder(PosIntegration $integration, string $externalOrderId): PosOrderResult
    {
        if ($this->usesStubMode($integration)) {
            return new PosOrderResult(true, externalOrderId: $externalOrderId, message: 'Stub mode: order cancelled.');
        }

        $endpoint = $this->baseUrl($integration);
        if ($endpoint === '') {
            return new PosOrderResult(false, message: 'API endpoint is not configured.');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->authHeaders($integration))
                ->delete(rtrim($endpoint, '/').$this->ordersPath($integration).'/'.$externalOrderId);

            if (! $response->successful()) {
                return new PosOrderResult(false, message: $this->sanitizeMessage($response->json('message') ?? 'POS cancellation failed.'));
            }

            return new PosOrderResult(true, externalOrderId: $externalOrderId, metadata: $this->safeResponse($response->json()));
        } catch (ConnectionException) {
            return new PosOrderResult(false, message: 'POS unreachable (timeout or network error).');
        } catch (RequestException $e) {
            return new PosOrderResult(false, message: $this->sanitizeMessage($e->getMessage()));
        }
    }

    public function getOrderStatus(PosIntegration $integration, string $externalOrderId): PosOrderStatusResult
    {
        if ($this->usesStubMode($integration)) {
            return new PosOrderStatusResult(true, status: 'open', message: 'Stub mode.');
        }

        $endpoint = $this->baseUrl($integration);
        if ($endpoint === '') {
            return new PosOrderStatusResult(false, message: 'API endpoint is not configured.');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->authHeaders($integration))
                ->get(rtrim($endpoint, '/').$this->ordersPath($integration).'/'.$externalOrderId);

            if (! $response->successful()) {
                return new PosOrderStatusResult(false, message: $this->sanitizeMessage($response->json('message') ?? 'Status lookup failed.'));
            }

            return new PosOrderStatusResult(
                success: true,
                status: (string) ($response->json('status') ?? 'unknown'),
                metadata: $this->safeResponse($response->json()),
            );
        } catch (ConnectionException) {
            return new PosOrderStatusResult(false, message: 'POS unreachable (timeout or network error).');
        } catch (RequestException $e) {
            return new PosOrderStatusResult(false, message: $this->sanitizeMessage($e->getMessage()));
        }
    }

    protected function baseUrl(PosIntegration $integration): string
    {
        return trim((string) $integration->api_endpoint);
    }

    protected function usesStubMode(PosIntegration $integration): bool
    {
        if (config('pos.force_stub', false)) {
            return true;
        }

        $credentials = $integration->credentials ?? [];

        return (bool) ($credentials['stub_mode'] ?? false)
            || $this->baseUrl($integration) === '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeOrder(PosIntegration $integration, PosOrderPayload $payload): array
    {
        return [
            'idempotency_key' => $payload->idempotencyKey,
            'order_number' => $payload->orderNumber,
            'store_location_id' => $integration->store_location_id,
            'table' => [
                'code' => $payload->tableCode,
                'name' => $payload->tableName,
            ],
            'customer_name' => $payload->customerName,
            'notes' => $payload->notes,
            'subtotal' => $payload->subtotal,
            'total' => $payload->total,
            'currency' => $payload->currency,
            'lines' => array_map(fn ($line) => [
                'product_id' => $line->externalProductId,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'notes' => $line->notes,
                'modifiers' => $line->modifiers,
            ], $payload->lines),
            'metadata' => $payload->metadata,
        ];
    }

  /**
   * @param  mixed  $payload
   * @return array<string, mixed>
   */
    protected function safeResponse(mixed $payload): array
    {
        return is_array($payload) ? LogRedactor::redact($payload) : [];
    }

    protected function sanitizeMessage(?string $message): string
    {
        if ($message === null || $message === '') {
            return 'POS request failed.';
        }

        return (string) LogRedactor::redact($message);
    }
}
