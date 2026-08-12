<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Location::query()->orderBy('zone')->orderBy('name');

        if ($request->filled('type')) {
            $query->whereIn('type', array_map('trim', explode(',', (string) $request->query('type'))));
        }

        if ($request->filled('zone')) {
            $query->where('zone', $request->query('zone'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q')->toString().'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', $q)->orWhere('code', 'like', $q);
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['code'] = $this->resolveStableCode($data['type'], $data['name']);

        $location = Location::create($data);
        AuditLogger::log('location.created', $location, null, $location->toArray());

        return response()->json($location, 201);
    }

    public function show(Location $location): JsonResponse
    {
        return response()->json($location);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $old = $location->toArray();
        $data = $this->validated($request, false);
        $name = $data['name'] ?? $location->name;
        $type = $data['type'] ?? $location->type;
        $data['code'] = $this->resolveStableCode($type, $name, $location->id);

        $location->update($data);
        AuditLogger::log('location.updated', $location, $old, $location->toArray());

        return response()->json($location);
    }

    public function destroy(Location $location): JsonResponse
    {
        AuditLogger::log('location.deleted', $location, $location->toArray());
        $location->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Re-sync QR payload URLs. Code stays {type}{number} and never becomes random.
     */
    public function regenerateQr(Location $location): JsonResponse
    {
        $old = $location->code;
        $code = $this->resolveStableCode($location->type, $location->name, $location->id);

        if ($old !== $code) {
            $location->update(['code' => $code]);
            AuditLogger::log('location.qr_regenerated', $location, ['code' => $old], ['code' => $location->code]);
        }

        $tenant = TenantContext::get();
        $orderUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            .'/t/'.($tenant?->slug ?? '').'/q/'.$location->code;

        return response()->json([
            ...$location->fresh()->toArray(),
            'order_url' => $orderUrl,
            'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='.urlencode($orderUrl),
        ]);
    }

    private function resolveStableCode(string $type, string $name, ?int $ignoreId = null): string
    {
        try {
            $code = Location::stableCode($type, $name);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'name' => ['Il nome deve contenere un numero (es. "Ombrellone 12").'],
            ]);
        }

        $exists = Location::query()
            ->where('tenant_id', TenantContext::id())
            ->where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ["Lo slug QR \"{$code}\" è già usato da un'altra postazione."],
            ]);
        }

        return $code;
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $data = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('locations', 'slug')->where(fn ($q) => $q->where('tenant_id', TenantContext::id()))->ignore($request->route('location')?->id)],
            'type' => [$creating ? 'required' : 'sometimes', 'in:table,umbrella,sunbed'],
            'zone' => ['nullable', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        // Code is always derived from type + number in name — never client-supplied / random.
        unset($data['code']);

        return $data;
    }
}
