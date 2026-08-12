<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Tenant::query()->with('subscription')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'unique:tenants,slug', 'alpha_dash'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_locale' => ['nullable', 'string', 'max:5'],
            'branding' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['settings'] = Tenant::defaultSettings($data['settings'] ?? []);
        $tenant = Tenant::create($data);
        AuditLogger::log('tenant.created', $tenant, null, $tenant->toArray());

        return response()->json($tenant, 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('subscription');

        return response()->json($tenant);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug,'.$tenant->id],
            'timezone' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_locale' => ['nullable', 'string', 'max:5'],
            'branding' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $tenant->toArray();
        $wasActive = $tenant->is_active;
        $settingsPatch = $data['settings'] ?? [];
        if (array_key_exists('is_active', $data)) {
            $settingsPatch['admin_suspended'] = ! ($data['is_active'] ?? true);
        }
        if ($settingsPatch !== []) {
            $data['settings'] = $tenant->mergeSettings($settingsPatch);
        }
        $tenant->update($data);
        $tenant->refresh();

        if ($wasActive && ! $tenant->is_active) {
            $tenant->revokeAllStaffTokens();
            AuditLogger::log('tenant.deactivated', $tenant, $old, $tenant->toArray());
        } elseif (! $wasActive && $tenant->is_active) {
            AuditLogger::log('tenant.activated', $tenant, $old, $tenant->toArray());
        } else {
            AuditLogger::log('tenant.updated', $tenant, $old, $tenant->toArray());
        }

        return response()->json($tenant);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        AuditLogger::log('tenant.deleted', $tenant, $tenant->toArray());
        $tenant->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
