<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosMapping;
use App\Services\AuditLogger;
use App\Services\Pos\PosIntegrationService;
use App\Services\Pos\PosMappingLabelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosMappingController extends Controller
{
    public function __construct(
        private readonly PosIntegrationService $integrations,
        private readonly PosMappingLabelResolver $labels,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $integration = $this->integrations->getOrCreateForTenant();
        $locale = $this->labels->requestLocale(
            $request->header('X-Locale'),
            $request->query('locale'),
        );

        $query = PosMapping::query()
            ->where('pos_integration_id', $integration->id)
            ->orderBy('entity_type')
            ->orderBy('local_id');

        if ($entityType = $request->query('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        $paginator = $query->paginate(100);
        $paginator->getCollection()->transform(function (PosMapping $mapping) use ($locale) {
            $data = $mapping->toArray();
            $data['local_label'] = $this->labels->resolve(
                (string) $mapping->entity_type,
                (int) $mapping->local_id,
                $locale,
            );
            $data['local_name'] = $this->labels->nameMap(
                (string) $mapping->entity_type,
                (int) $mapping->local_id,
            );

            return $data;
        });

        return response()->json($paginator);
    }

    public function entities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(PosMapping::ENTITY_TYPES)],
        ]);

        $locale = $this->labels->requestLocale(
            $request->header('X-Locale'),
            $request->query('locale'),
        );

        return response()->json([
            'data' => $this->labels->listEntities($data['entity_type'], $locale),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $integration = $this->integrations->getOrCreateForTenant();

        $data = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(PosMapping::ENTITY_TYPES)],
            'local_id' => ['required', 'integer', 'min:1'],
            'external_id' => ['required', 'string', 'max:120'],
            'external_sku' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $mapping = PosMapping::query()->updateOrCreate(
            [
                'pos_integration_id' => $integration->id,
                'entity_type' => $data['entity_type'],
                'local_id' => $data['local_id'],
            ],
            [
                'external_id' => $data['external_id'],
                'external_sku' => $data['external_sku'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );

        AuditLogger::log('pos_mapping.saved', $mapping, null, $mapping->toArray());

        return response()->json($mapping, 201);
    }

    public function destroy(PosMapping $posMapping): JsonResponse
    {
        AuditLogger::log('pos_mapping.deleted', $posMapping, $posMapping->toArray(), null);
        $posMapping->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $integration = $this->integrations->getOrCreateForTenant();

        $data = $request->validate([
            'mappings' => ['required', 'array', 'min:1', 'max:200'],
            'mappings.*.entity_type' => ['required', 'string', Rule::in(PosMapping::ENTITY_TYPES)],
            'mappings.*.local_id' => ['required', 'integer', 'min:1'],
            'mappings.*.external_id' => ['required', 'string', 'max:120'],
            'mappings.*.external_sku' => ['nullable', 'string', 'max:120'],
            'mappings.*.metadata' => ['nullable', 'array'],
        ]);

        $saved = [];
        foreach ($data['mappings'] as $row) {
            $saved[] = PosMapping::query()->updateOrCreate(
                [
                    'pos_integration_id' => $integration->id,
                    'entity_type' => $row['entity_type'],
                    'local_id' => $row['local_id'],
                ],
                [
                    'external_id' => $row['external_id'],
                    'external_sku' => $row['external_sku'] ?? null,
                    'metadata' => $row['metadata'] ?? null,
                ]
            );
        }

        return response()->json(['data' => $saved]);
    }
}
