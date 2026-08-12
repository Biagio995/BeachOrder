<?php

namespace App\Contracts\Pos;

use App\Models\Order;
use App\Models\PosIntegration;

interface POSAdapterInterface
{
    public function provider(): string;

    public function authenticate(PosIntegration $integration): PosConnectionResult;

    public function testConnection(PosIntegration $integration): PosConnectionResult;

  /**
   * @return list<PosExternalProduct>
   */
    public function getProducts(PosIntegration $integration): array;

    public function syncProduct(PosIntegration $integration, PosProductMapping $mapping): PosProductSyncResult;

    public function createOrder(PosIntegration $integration, Order $order, PosOrderPayload $payload): PosOrderResult;

    public function updateOrder(PosIntegration $integration, string $externalOrderId, PosOrderPayload $payload): PosOrderResult;

    public function cancelOrder(PosIntegration $integration, string $externalOrderId): PosOrderResult;

    public function getOrderStatus(PosIntegration $integration, string $externalOrderId): PosOrderStatusResult;
}
