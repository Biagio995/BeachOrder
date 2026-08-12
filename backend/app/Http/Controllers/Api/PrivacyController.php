<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataSubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class PrivacyController extends Controller
{
    public function __construct(
        private readonly DataSubjectService $dataSubjectService,
    ) {}

    /**
     * Public metadata: retention periods, controller/processor info, data inventory summary.
     */
    public function info(): JsonResponse
    {
        $retention = config('privacy.retention', []);
        $inventory = config('privacy.data_inventory', []);

        return response()->json([
            'controller' => config('privacy.controller'),
            'processor' => config('privacy.processor'),
            'retention' => $retention,
            'data_inventory' => array_map(function (array $item) use ($retention) {
                $key = $item['retention_key'] ?? null;
                $item['retention_days'] = $key ? ($retention[$key] ?? null) : null;

                return $item;
            }, $inventory),
            'legal_documents' => [
                'privacy' => '/legal/privacy',
                'terms' => '/legal/terms',
                'cookies' => '/legal/cookies',
                'dpa' => '/legal/dpa',
                'data_processing_roles' => '/legal/data-processing-roles',
            ],
        ]);
    }

    /**
     * Serve a legal document in the requested locale (markdown).
     */
    public function legalDocument(string $document, Request $request): Response
    {
        $allowed = ['privacy', 'terms', 'cookies', 'dpa', 'data-processing-roles'];
        if (! in_array($document, $allowed, true)) {
            abort(404);
        }

        $locale = $request->query('locale', 'it');
        if (! in_array($locale, ['it', 'en', 'el', 'de'], true)) {
            $locale = 'it';
        }

        $docsRoot = dirname(base_path()).'/docs/legal';
        $path = "{$docsRoot}/{$document}.{$locale}.md";
        if (! File::exists($path)) {
            $path = "{$docsRoot}/{$document}.it.md";
        }
        if (! File::exists($path)) {
            abort(404, 'Document not found');
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Full data inventory audit (admin only).
     */
    public function inventory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        return response()->json([
            'inventory' => config('privacy.data_inventory'),
            'retention' => config('privacy.retention'),
            'controller' => config('privacy.controller'),
            'processor' => config('privacy.processor'),
            'storage_keys' => [
                'frontend' => [
                    'bo_token' => 'Staff auth token (localStorage)',
                    'bo_tenant_slug' => 'Active tenant slug (localStorage)',
                    'bo_session' => 'Anonymous customer session UUID (localStorage)',
                    'bo_access_*' => 'QR access token (sessionStorage)',
                    'bo_cart:*' => 'Cart contents (localStorage)',
                    'bo_active_order:*' => 'Active order reference (localStorage)',
                    'bo_locale' => 'Language preference (localStorage)',
                    'bo_cookie_consent' => 'Cookie consent choice (localStorage)',
                ],
            ],
        ]);
    }

    /**
     * Export personal data for the authenticated staff user (GDPR Art. 15/20).
     */
    public function exportMe(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(
            $this->dataSubjectService->exportStaffUser($user)
        );
    }

    /**
     * Self-service account deletion for staff users (GDPR Art. 17).
     */
    public function deleteMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['required', 'accepted'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Super admin accounts cannot be self-deleted.',
            ], 422);
        }

        if (! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect.',
            ], 422);
        }

        if ($user->isAdmin() && $user->tenant_id) {
            $otherAdmins = \App\Models\User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('role', \App\Models\User::ROLE_ADMIN)
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->exists();

            if (! $otherAdmins) {
                return response()->json([
                    'message' => 'Cannot delete the last active admin. Assign another admin first.',
                ], 422);
            }
        }

        $this->dataSubjectService->deleteStaffUser($user);

        return response()->json(['message' => 'Account deleted']);
    }

    /**
     * Erase anonymous customer session data within a tenant.
     */
    public function eraseCustomerSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_session' => ['required', 'uuid'],
        ]);

        $tenant = \App\Support\TenantContext::require();

        $result = $this->dataSubjectService->eraseCustomerSession(
            $tenant->id,
            $data['customer_session'],
        );

        return response()->json([
            'message' => 'Session data erased',
            'result' => $result,
        ]);
    }
}
