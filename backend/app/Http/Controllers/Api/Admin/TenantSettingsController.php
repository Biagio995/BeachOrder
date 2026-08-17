<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload(TenantContext::get()));
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = TenantContext::get();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_locale' => ['nullable', 'string', 'max:5'],
            'branding' => ['nullable', 'array'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
            'branding.accent_color' => ['nullable', 'string', 'max:20'],
            'branding.secondary_color' => ['nullable', 'string', 'max:20'],
            'branding.tagline' => ['nullable', 'string', 'max:200'],
            'branding.logo_path' => ['nullable', 'string', 'max:500'],
            'branding.logo_url' => ['nullable', 'string', 'max:500'],
            'branding.favicon_path' => ['nullable', 'string', 'max:500'],
            'branding.favicon_url' => ['nullable', 'string', 'max:500'],
            'branding.menu_header_path' => ['nullable', 'string', 'max:500'],
            'branding.menu_header_url' => ['nullable', 'string', 'max:500'],
            'branding.menu_cover_path' => ['nullable', 'string', 'max:500'],
            'branding.menu_cover_url' => ['nullable', 'string', 'max:500'],
            'settings' => ['nullable', 'array'],
            'settings.loyalty_enabled' => ['nullable', 'boolean'],
            'settings.online_payments_enabled' => ['nullable', 'boolean'],
            'settings.country' => ['nullable', 'string', 'size:2'],
            'settings.nexi' => ['nullable', 'array'],
            'settings.nexi.alias' => ['nullable', 'string', 'max:30'],
            'settings.nexi.secret_key' => ['nullable', 'string', 'max:200'],
            'settings.nexi.environment' => ['nullable', 'string', 'in:test,production'],
            'settings.fiscal' => ['nullable', 'array'],
            'settings.fiscal.enabled' => ['nullable', 'boolean'],
            'settings.fiscal.provider' => ['nullable', 'string', Rule::in(Tenant::FISCAL_PROVIDERS)],
            'settings.fiscal.mode' => ['nullable', 'string', Rule::in(Tenant::FISCAL_MODES)],
            'settings.fiscal.device_id' => ['nullable', 'string', 'max:120'],
            'settings.fiscal.credentials_ref' => ['nullable', 'string', 'max:200'],
            'settings.fiscal.auto_fiscalize_on_pay' => ['nullable', 'boolean'],
            'settings.pos' => ['nullable', 'array'],
            'settings.pos.enabled' => ['nullable', 'boolean'],
            'settings.pos.provider' => ['nullable', 'string', Rule::in(Tenant::POS_PROVIDERS)],
            'settings.pos.terminal_id' => ['nullable', 'string', 'max:120'],
            'settings.pos.credentials_ref' => ['nullable', 'string', 'max:200'],
            'settings.printing' => ['nullable', 'array'],
            'settings.printing.enabled' => ['nullable', 'boolean'],
            'settings.printing.driver' => ['nullable', 'string', Rule::in(Tenant::PRINT_DRIVERS)],
            'settings.printing.credentials_ref' => ['nullable', 'string', 'max:200'],
            'settings.printing.stations' => ['nullable', 'array'],
            'settings.printing.stations.kitchen' => ['nullable', 'array'],
            'settings.printing.stations.kitchen.host' => ['nullable', 'string', 'max:120'],
            'settings.printing.stations.kitchen.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'settings.printing.stations.kitchen.copies' => ['nullable', 'integer', 'min:1', 'max:5'],
            'settings.printing.stations.bar' => ['nullable', 'array'],
            'settings.printing.stations.bar.host' => ['nullable', 'string', 'max:120'],
            'settings.printing.stations.bar.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'settings.printing.stations.bar.copies' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $old = $tenant->toArray();

        if (array_key_exists('branding', $data)) {
            $data['branding'] = TenantBranding::normalizeInput($data['branding'] ?? []);
            $data['branding'] = array_replace($tenant->branding ?? [], $data['branding']);
        }

        if (array_key_exists('settings', $data)) {
            $data['settings'] = $tenant->mergeSettings($data['settings'] ?? []);
        }

        $tenant->fill($data);
        $tenant->save();

        AuditLogger::log('tenant.settings_updated', $tenant, $old, $tenant->toArray());

        return response()->json($this->payload($tenant));
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        return $this->uploadBrandingAsset($request, 'logo', 'logo_path');
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        return $this->uploadBrandingAsset($request, 'favicon', 'favicon_path');
    }

    public function uploadMenuHeader(Request $request): JsonResponse
    {
        return $this->uploadBrandingAsset($request, 'menu_header', 'menu_header_path');
    }

    private function uploadBrandingAsset(Request $request, string $field, string $pathKey): JsonResponse
    {
        $request->validate([
            $field => ['required', 'image', 'max:4096'],
        ]);

        $tenant = TenantContext::get();
        $path = $request->file($field)->store(
            'tenants/'.TenantContext::id().'/branding/'.$field,
            config('filesystems.uploads_disk', 'public')
        );

        $branding = $tenant->branding ?? [];
        $old = ['branding' => $branding];
        $branding[$pathKey] = $path;
        $urlKey = str_replace('_path', '_url', $pathKey);
        unset($branding[$urlKey]);

        $tenant->update(['branding' => $branding]);
        AuditLogger::log('tenant.branding_asset_uploaded', $tenant, $old, ['branding' => $branding, 'asset' => $field]);

        return response()->json($this->payload($tenant));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'restaurant_name' => $tenant->name,
            'slug' => $tenant->slug,
            'timezone' => $tenant->timezone,
            'currency' => $tenant->currency,
            'default_locale' => $tenant->default_locale,
            'branding' => TenantBranding::resolve($tenant->branding),
            'settings' => Tenant::defaultSettings($tenant->settings ?? []),
        ];
    }
}
