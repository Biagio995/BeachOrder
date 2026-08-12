<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LocationAccessToken;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Models\WaiterCall;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class DataSubjectService
{
    /**
     * Export all personal data for a staff user (GDPR Art. 15 / 20).
     *
     * @return array<string, mixed>
     */
    public function exportStaffUser(User $user): array
    {
        $user->load('tenant');

        return [
            'exported_at' => now()->toIso8601String(),
            'subject_type' => 'staff_user',
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'tenant' => $user->tenant ? [
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
            ] : null,
            'audit_logs' => AuditLog::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['action', 'created_at', 'ip_address'])
                ->toArray(),
        ];
    }

    /**
     * Delete a staff user account and revoke all sessions (GDPR Art. 17).
     */
    public function deleteStaffUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            AuditLogger::log('privacy.account_deleted', $user, $user->only(['id', 'email', 'role']), null);

            $user->revokeAllTokens();
            $user->delete();
        });
    }

    /**
     * Erase/anonymize all data linked to an anonymous customer session within a tenant.
     *
     * @return array<string, int>
     */
    public function eraseCustomerSession(int $tenantId, string $customerSession): array
    {
        TenantContext::set(Tenant::query()->findOrFail($tenantId));

        $orders = Order::query()
            ->where('customer_session', $customerSession)
            ->update([
                'customer_name' => null,
                'customer_session' => null,
                'notes' => null,
            ]);

        $calls = WaiterCall::query()
            ->where('customer_session', $customerSession)
            ->delete();

        $loyaltyIds = LoyaltyAccount::query()
            ->where('customer_session', $customerSession)
            ->pluck('id');

        $transactions = 0;
        if ($loyaltyIds->isNotEmpty()) {
            $transactions = LoyaltyTransaction::query()
                ->whereIn('loyalty_account_id', $loyaltyIds)
                ->delete();
        }

        $loyalty = LoyaltyAccount::query()
            ->where('customer_session', $customerSession)
            ->delete();

        $tokens = LocationAccessToken::query()
            ->where('customer_session', $customerSession)
            ->delete();

        AuditLogger::log('privacy.session_erased', null, null, [
            'customer_session' => substr($customerSession, 0, 8).'…',
            'orders_anonymized' => $orders,
            'waiter_calls_deleted' => $calls,
        ]);

        return [
            'orders_anonymized' => $orders,
            'waiter_calls_deleted' => $calls,
            'loyalty_accounts_deleted' => $loyalty,
            'loyalty_transactions_deleted' => $transactions,
            'access_tokens_deleted' => $tokens,
        ];
    }
}
