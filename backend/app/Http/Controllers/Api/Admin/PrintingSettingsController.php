<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Printing\PrintService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintingSettingsController extends Controller
{
    public function __construct(private PrintService $printing) {}

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'station' => ['required', 'string', 'in:kitchen,bar'],
        ]);

        $tenant = TenantContext::get();

        try {
            $result = $this->printing->printTest($tenant, $data['station']);

            return response()->json([
                'message' => 'Test print sent.',
                'preview' => $result['text'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'station' => ['required', 'string', 'in:kitchen,bar'],
        ]);

        $tenant = TenantContext::get();
        $preview = $this->printing->previewTest($tenant, $data['station']);

        return response()->json(['preview' => $preview]);
    }
}
