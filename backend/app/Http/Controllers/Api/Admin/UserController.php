<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use App\Support\RolePermissions;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->when(TenantContext::id(), fn ($q) => $q->where('tenant_id', TenantContext::id()))
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(RolePermissions::assignableRoles())],
            'staff_position' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_STAFF),
                'nullable',
                Rule::in(User::STAFF_POSITIONS),
            ],
            'is_active' => ['nullable', 'boolean'],
            'location_id' => ['nullable', TenantRules::exists('locations')],
        ]);

        if (($data['role'] ?? null) !== User::ROLE_STAFF) {
            $data['staff_position'] = null;
        }

        $data['tenant_id'] = TenantContext::id();
        $user = User::create($data);
        $user->sendEmailVerificationNotification();
        AuditLogger::log('user.created', $user, null, $user->toArray());

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertSameTenant($user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::in(RolePermissions::assignableRoles())],
            'staff_position' => [
                Rule::requiredIf(fn () => ($request->input('role') ?? $user->role) === User::ROLE_STAFF),
                'nullable',
                Rule::in(User::STAFF_POSITIONS),
            ],
            'is_active' => ['nullable', 'boolean'],
            'location_id' => ['nullable', TenantRules::exists('locations')],
        ]);

        $role = $data['role'] ?? $user->role;
        if ($role !== User::ROLE_STAFF) {
            $data['staff_position'] = null;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $old = $user->toArray();
        $user->update($data);

        if (! empty($data['password'])) {
            $user->revokeAllTokens();
        }

        AuditLogger::log('user.updated', $user, $old, $user->toArray());

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->assertSameTenant($user);

        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }

        $user->revokeAllTokens();
        AuditLogger::log('user.deleted', $user, $user->toArray());
        $user->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function assertSameTenant(User $user): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId || (int) $user->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $actor = request()->user();
        if ($actor && ! $actor->canAccessTenant($tenantId)) {
            abort(403, 'Tenant access denied');
        }
    }
}
