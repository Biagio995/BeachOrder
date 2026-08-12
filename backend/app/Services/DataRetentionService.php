<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LocationAccessToken;
use App\Models\LoyaltyAccount;
use App\Models\Order;
use App\Models\WaiterCall;
use Illuminate\Support\Facades\DB;

class DataRetentionService
{
    /**
     * Run all configured retention purges/anonymizations.
     *
     * @return array<string, int>
     */
    public function purgeAll(): array
    {
        return [
            'orders_anonymized' => $this->anonymizeOldOrders(),
            'waiter_calls_deleted' => $this->deleteOldWaiterCalls(),
            'access_tokens_deleted' => $this->deleteExpiredAccessTokens(),
            'loyalty_accounts_deleted' => $this->deleteInactiveLoyaltyAccounts(),
            'audit_logs_deleted' => $this->deleteOldAuditLogs(),
            'expired_tokens_deleted' => $this->deleteExpiredSanctumTokens(),
            'password_resets_deleted' => $this->deleteOldPasswordResets(),
            'sessions_deleted' => $this->deleteOldSessions(),
        ];
    }

    public function anonymizeOldOrders(): int
    {
        $days = config('privacy.retention.orders_days');
        $cutoff = now()->subDays($days);

        return Order::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($q) {
                $q->whereNotNull('customer_name')
                    ->orWhereNotNull('customer_session')
                    ->orWhereNotNull('notes');
            })
            ->update([
                'customer_name' => null,
                'customer_session' => null,
                'notes' => null,
            ]);
    }

    public function deleteOldWaiterCalls(): int
    {
        $days = config('privacy.retention.waiter_calls_days');
        $cutoff = now()->subDays($days);

        return WaiterCall::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    public function deleteExpiredAccessTokens(): int
    {
        $days = config('privacy.retention.access_tokens_days');
        $cutoff = now()->subDays($days);

        return LocationAccessToken::query()
            ->where(function ($q) use ($cutoff) {
                $q->where('expires_at', '<', now())
                    ->orWhere('created_at', '<', $cutoff);
            })
            ->delete();
    }

    public function deleteInactiveLoyaltyAccounts(): int
    {
        $days = config('privacy.retention.loyalty_inactive_days');
        $cutoff = now()->subDays($days);

        return LoyaltyAccount::query()
            ->where('updated_at', '<', $cutoff)
            ->where('points', 0)
            ->delete();
    }

    public function deleteOldAuditLogs(): int
    {
        $days = config('privacy.retention.audit_logs_days');
        $cutoff = now()->subDays($days);

        return AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    public function deleteExpiredSanctumTokens(): int
    {
        $days = config('privacy.retention.expired_tokens_days');
        $cutoff = now()->subDays($days);

        return DB::table('personal_access_tokens')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoff)
            ->delete();
    }

    public function deleteOldPasswordResets(): int
    {
        $hours = config('privacy.retention.password_reset_tokens_hours');
        $cutoff = now()->subHours($hours);

        return DB::table('password_reset_tokens')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    public function deleteOldSessions(): int
    {
        $days = config('privacy.retention.sessions_days');
        $cutoff = now()->subDays($days);

        return DB::table('sessions')
            ->where('last_activity', '<', $cutoff->timestamp)
            ->delete();
    }
}
