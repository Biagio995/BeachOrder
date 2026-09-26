<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Monitoring\BackupMonitor;
use App\Services\Monitoring\QueueHeartbeat;
use App\Services\Monitoring\RequestMetrics;
use App\Services\Monitoring\WebhookMonitor;
use App\Support\LogRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * US-09 — Monitoring acceptance tests.
 */
class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'monitoring.frontend_check_enabled' => false,
        ]);
    }

    public function test_health_endpoint_returns_structured_checks(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'api',
                    'database',
                    'frontend',
                    'background_jobs',
                    'payment_webhooks',
                    'backup_jobs',
                    'error_rate',
                    'response_time',
                ],
                'metrics' => [
                    'sample_count',
                    'error_rate',
                    'avg_response_time_ms',
                    'p95_response_time_ms',
                ],
            ])
            ->assertJsonPath('checks.api.status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok');
    }

    public function test_health_endpoint_is_public_and_includes_request_id(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_request_metrics_track_error_rate_and_response_time(): void
    {
        Cache::flush();

        RequestMetrics::record(200, 50, 'test.route');
        RequestMetrics::record(500, 120, 'test.route');

        $snapshot = RequestMetrics::snapshot();

        $this->assertSame(2, $snapshot['sample_count']);
        $this->assertSame(0.5, $snapshot['error_rate']);
        $this->assertSame(85.0, $snapshot['avg_response_time_ms']);
    }

    public function test_webhook_monitor_records_success_and_failure(): void
    {
        Cache::flush();

        WebhookMonitor::recordSuccess('stripe', 'payment_intent.succeeded');
        WebhookMonitor::recordFailure('stripe', 'payment_intent.failed', 'invalid signature');

        $health = WebhookMonitor::health();

        $this->assertSame('ok', $health['status']);
        $this->assertSame('stripe', $health['provider']);
        $this->assertNotNull($health['last_success_at']);
    }

    public function test_backup_monitor_reads_from_backup_logs(): void
    {
        BackupLog::query()->create([
            'filename' => 'sqlite_test.sql.gz.enc',
            'driver' => 'sqlite',
            'status' => BackupLog::STATUS_COMPLETED,
            'size_bytes' => 2048,
            'encrypted' => true,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $health = BackupMonitor::health();

        $this->assertSame('ok', $health['status']);
        $this->assertNotNull($health['last_backup_at']);
        $this->assertSame(2048, $health['bytes']);
    }

    public function test_monitor_health_command_runs_successfully(): void
    {
        $this->artisan('monitor:health')->assertSuccessful();
    }

    public function test_queue_worker_events_write_heartbeat_for_health_check(): void
    {
        Cache::flush();

        $before = $this->getJson('/api/health');
        $before->assertOk()
            ->assertJsonPath('checks.background_jobs.worker_alive', false);

        event(new \Illuminate\Queue\Events\WorkerStarting('database', 'default', new \Illuminate\Queue\WorkerOptions));

        $after = $this->getJson('/api/health');
        $after->assertOk()
            ->assertJsonPath('checks.background_jobs.worker_alive', true);
    }

    public function test_monitor_health_does_not_fake_queue_heartbeat(): void
    {
        Cache::flush();

        $this->artisan('monitor:health')->assertSuccessful();

        $this->assertNull(Cache::get((string) config('monitoring.queue_heartbeat_key')));
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('checks.background_jobs.worker_alive', false);
    }

    public function test_worker_alive_false_when_heartbeat_older_than_max_age(): void
    {
        Cache::flush();
        config(['monitoring.queue_heartbeat_max_age_seconds' => 8]);

        QueueHeartbeat::touch();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('checks.background_jobs.worker_alive', true);

        $this->travel(9)->seconds();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('checks.background_jobs.worker_alive', false);
    }

    public function test_worker_alive_true_within_max_age(): void
    {
        Cache::flush();
        config(['monitoring.queue_heartbeat_max_age_seconds' => 30]);

        QueueHeartbeat::touch();
        $this->travel(10)->seconds();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('checks.background_jobs.worker_alive', true);
    }

    public function test_queue_heartbeat_touch_swallows_cache_failures(): void
    {
        $logged = [];
        Log::listen(function (object $event) use (&$logged): void {
            $logged[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context ?? [],
            ];
        });

        Cache::shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('cache unavailable'));

        QueueHeartbeat::touch();

        $this->assertTrue(
            collect($logged)->contains(function (array $entry): bool {
                return ($entry['level'] ?? null) === 'warning'
                    && str_contains((string) ($entry['message'] ?? ''), 'Failed to write queue worker heartbeat')
                    && ($entry['context']['message'] ?? null) === 'cache unavailable';
            }),
            'Expected a warning log when the cache write fails'
        );
    }

    public function test_log_redactor_masks_sensitive_and_pii_fields(): void
    {
        $redacted = LogRedactor::redact([
            'password' => 'secret123',
            'email' => 'user@example.com',
            'customer_name' => 'Mario Rossi',
            'token' => 'abc123',
            'nested' => ['api_key' => 'key-live'],
        ]);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('u***@example.com', $redacted['email']);
        $this->assertSame('Ma***i', $redacted['customer_name']);
        $this->assertSame('[REDACTED]', $redacted['token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['api_key']);
    }

    public function test_audit_logger_redacts_pii_from_audit_entries(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Monitor Beach',
            'slug' => 'monitor-beach',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Audit User',
            'email' => 'audit@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/login', [
            'email' => 'audit@example.com',
            'password' => 'password',
        ])->assertOk();

        $log = AuditLog::query()->where('action', 'auth.login')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('email', $log->new_values ?? []);
        $this->assertSame($user->id, $log->new_values['user_id'] ?? null);
    }

    public function test_health_endpoint_is_excluded_from_metrics_noise(): void
    {
        Cache::flush();

        $this->getJson('/api/health')->assertOk();

        $snapshot = RequestMetrics::snapshot();
        $this->assertSame(0, $snapshot['sample_count']);
    }
}
