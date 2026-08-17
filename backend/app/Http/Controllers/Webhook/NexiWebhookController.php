<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\NexiXPayService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class NexiWebhookController extends Controller
{
    public function __construct(
        private NexiXPayService $nexi,
    ) {}

    public function notify(Request $request, string $tenant): Response
    {
        $record = Tenant::query()->where('slug', $tenant)->firstOrFail();
        TenantContext::set($record);

        $payload = $request->all();
        $order = $this->resolveOrder($payload);

        if (! $order) {
            Log::channel('payments')->warning('nexi.notify_orphan', [
                'tenant' => $tenant,
                'codTrans' => $payload['codTrans'] ?? null,
            ]);

            return response('OK', 200);
        }

        $this->nexi->handleOutcome($order, $payload);

        return response('OK', 200);
    }

    public function customerReturn(Request $request, string $tenant, Order $order): RedirectResponse
    {
        TenantContext::set(Tenant::query()->where('slug', $tenant)->firstOrFail());

        $session = (string) $request->query('session', '');
        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
        $statusUrl = "{$frontendBase}/t/{$tenant}/order/{$order->id}?".http_build_query([
            'session' => $session,
        ]);

        if ($session === '' || ! $order->customer_session || ! hash_equals($order->customer_session, $session)) {
            return redirect()->away("{$statusUrl}&payment=error");
        }

        $paid = $this->nexi->handleOutcome($order, $request->all());
        $query = $paid ? 'payment=success' : 'payment=failed';

        return redirect()->away("{$statusUrl}&{$query}");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOrder(array $payload): ?Order
    {
        $codTrans = (string) ($payload['codTrans'] ?? '');
        if ($codTrans === '') {
            return null;
        }

        $tenantId = TenantContext::id();

        return Order::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($codTrans) {
                $query->where('payment_reference', $codTrans)
                    ->orWhereRaw("REPLACE(payment_reference, '-', '') = ?", [$codTrans]);
            })
            ->first();
    }
}
