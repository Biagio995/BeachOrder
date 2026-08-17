<?php

namespace App\Services;

use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Pos\PosOrderSyncService;
use App\Services\Printing\PrintService;
use Illuminate\Support\Facades\Log;

class NexiXPayService
{
    private const TEST_GATEWAY = 'https://int-ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet';

    private const PRODUCTION_GATEWAY = 'https://ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet';

    public function isConfigured(?Tenant $tenant): bool
    {
        if (! $tenant) {
            return false;
        }

        $config = $this->config($tenant);

        return filled($config['alias'] ?? null) && filled($config['secret_key'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(Tenant $tenant): array
    {
        return (array) $tenant->setting('nexi', []);
    }

    public function gatewayUrl(Tenant $tenant): string
    {
        $environment = (string) ($this->config($tenant)['environment'] ?? 'test');

        return $environment === 'production' ? self::PRODUCTION_GATEWAY : self::TEST_GATEWAY;
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentSessionFor(Order $order, string $customerSession): array
    {
        $tenant = $order->tenant;
        abort_unless($tenant && $this->isConfigured($tenant), 503, 'Card payments are not configured');

        $config = $this->config($tenant);
        $codTrans = $this->codTransFor($order);
        $importo = (string) $this->amountInCents($order);
        $divisa = 'EUR';
        $secretKey = (string) $config['secret_key'];
        $mac = $this->initiationMac($codTrans, $divisa, $importo, $secretKey);

        $publicBase = rtrim((string) (config('app.public_url') ?: config('app.frontend_url')), '/');
        $returnQuery = http_build_query(['session' => $customerSession]);

        return [
            'type' => 'redirect',
            'provider' => 'nexi',
            'payment_status' => $order->payment_status,
            'amount' => (float) $order->total,
            'currency' => $divisa,
            'payment_reference' => $order->payment_reference,
            'gateway_url' => $this->gatewayUrl($tenant),
            'fields' => [
                'alias' => (string) $config['alias'],
                'importo' => $importo,
                'divisa' => $divisa,
                'codTrans' => $codTrans,
                'url' => "{$publicBase}/api/t/{$tenant->slug}/orders/{$order->id}/payment/nexi/return?{$returnQuery}",
                'url_back' => "{$publicBase}/t/{$tenant->slug}/order/{$order->id}?{$returnQuery}&payment=cancelled",
                'urlpost' => "{$publicBase}/api/webhooks/nexi/{$tenant->slug}",
                'mac' => $mac,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyOutcomeMac(array $payload, Tenant $tenant): bool
    {
        $secretKey = (string) ($this->config($tenant)['secret_key'] ?? '');
        if ($secretKey === '') {
            return false;
        }

        $expected = $this->outcomeMac(
            (string) ($payload['codTrans'] ?? ''),
            (string) ($payload['esito'] ?? ''),
            (string) ($payload['importo'] ?? ''),
            (string) ($payload['divisa'] ?? ''),
            (string) ($payload['data'] ?? ''),
            (string) ($payload['orario'] ?? ''),
            (string) ($payload['codAut'] ?? ''),
            $secretKey,
        );

        return hash_equals($expected, strtolower((string) ($payload['mac'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleOutcome(Order $order, array $payload): bool
    {
        $tenant = $order->tenant;
        if (! $tenant || ! $this->verifyOutcomeMac($payload, $tenant)) {
            Log::channel('payments')->warning('nexi.invalid_mac', [
                'order_id' => $order->id,
                'codTrans' => $payload['codTrans'] ?? null,
            ]);

            return false;
        }

        $esito = strtoupper((string) ($payload['esito'] ?? ''));

        if ($esito === 'OK') {
            $this->markPaid($order, (string) ($payload['codAut'] ?? ''));

            return true;
        }

        if (in_array($esito, ['CANCELLED', 'ERRORE', 'ERROR', 'KO'], true)) {
            $order->payment_status = 'failed';
            $order->payment_error = $esito === 'CANCELLED'
                ? 'Payment cancelled by customer.'
                : (string) ($payload['messaggio'] ?? 'Payment failed.');
            $order->save();

            try {
                event(new OrderUpdated($order));
            } catch (\Throwable) {
                //
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function receiptFor(Order $order): ?array
    {
        if ($order->payment_method !== 'card_online' || $order->payment_status !== 'paid') {
            return null;
        }

        return [
            'reference' => $order->payment_reference,
            'authorization_code' => $order->payment_authorization_code,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'amount' => (float) $order->total,
            'currency' => strtoupper($order->tenant?->currency ?? 'EUR'),
            'method' => $order->payment_method,
            'provider' => 'nexi',
        ];
    }

    public function markPaid(Order $order, string $authorizationCode = ''): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $order->payment_status = 'paid';
        $order->paid_at = now();
        $order->payment_error = null;

        if ($authorizationCode !== '') {
            $order->payment_authorization_code = $authorizationCode;
        }

        $order->save();
        $this->afterPaid($order->fresh(['items', 'location', 'tenant']));
    }

    public function afterPaid(Order $order): void
    {
        try {
            event(new OrderUpdated($order));
        } catch (\Throwable) {
            //
        }

        try {
            app(PrintService::class)->dispatchOrderPrint($order);
        } catch (\Throwable) {
            //
        }

        try {
            app(PosOrderSyncService::class)->queueForOrder($order);
        } catch (\Throwable) {
            //
        }
    }

    public function codTransFor(Order $order): string
    {
        $reference = preg_replace('/[^A-Za-z0-9]/', '', (string) ($order->payment_reference ?? ''));

        if ($reference === '') {
            $reference = 'BOPAY'.$order->id;
        }

        return substr($reference, 0, 30);
    }

    private function amountInCents(Order $order): int
    {
        return (int) round(((float) $order->total) * 100);
    }

    private function initiationMac(string $codTrans, string $divisa, string $importo, string $secretKey): string
    {
        return sha1("codTrans={$codTrans}divisa={$divisa}importo={$importo}{$secretKey}");
    }

    private function outcomeMac(
        string $codTrans,
        string $esito,
        string $importo,
        string $divisa,
        string $data,
        string $orario,
        string $codAut,
        string $secretKey,
    ): string {
        return sha1("codTrans={$codTrans}esito={$esito}importo={$importo}divisa={$divisa}data={$data}orario={$orario}codAut={$codAut}{$secretKey}");
    }
}
