<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosIntegration;
use App\Services\AuditLogger;
use App\Services\Pos\PosAdapterRegistry;
use App\Services\Pos\PosIntegrationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosIntegrationController extends Controller
{
    public function __construct(
        private readonly PosIntegrationService $integrations,
        private readonly PosAdapterRegistry $registry,
    ) {}

    public function show(): JsonResponse
    {
        $integration = $this->integrations->getOrCreateForTenant();
        $this->integrations->ensureWebhookSecret($integration);

        return response()->json($this->integrations->dashboardPayload($integration->fresh()));
    }

    public function update(Request $request): JsonResponse
    {
        $integration = $this->integrations->getOrCreateForTenant();

        $data = $request->validate([
            'provider' => ['sometimes', 'string', Rule::in(PosIntegration::PROVIDERS)],
            'api_endpoint' => ['nullable', 'string', 'max:500'],
            'store_location_id' => ['nullable', 'string', 'max:120'],
            'extra_params' => ['nullable', 'array'],
            'is_enabled' => ['sometimes', 'boolean'],
            'credentials' => ['nullable', 'array'],
            'credentials.api_key' => ['nullable', 'string', 'max:500'],
            'credentials.username' => ['nullable', 'string', 'max:120'],
            'credentials.password' => ['nullable', 'string', 'max:500'],
            'credentials.stub_mode' => ['nullable', 'boolean'],
        ]);

        $old = $integration->toArray();
        $integration = $this->integrations->update($integration, $data);
        $this->integrations->ensureWebhookSecret($integration);

        AuditLogger::log('pos_integration.updated', $integration, $old, $integration->toArray());

        return response()->json($this->integrations->dashboardPayload($integration));
    }

    public function testConnection(): JsonResponse
    {
        $integration = $this->integrations->getOrCreateForTenant();
        $integration = $this->integrations->testConnection($integration);

        return response()->json($this->integrations->dashboardPayload($integration));
    }

    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => $this->registry->providers(),
        ]);
    }
}
