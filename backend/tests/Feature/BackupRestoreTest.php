<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\Tenant;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseRestoreService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = database_path('testing-backup-'.uniqid('', true).'.sqlite');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'backup.enabled' => true,
            'backup.encrypt' => true,
            'backup.local_disk' => 'backups',
            'backup.remote_disk' => 's3',
            'backup.remote_path_prefix' => 'database/'.uniqid('', true),
            'backup.retention_days' => 30,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Storage::fake('backups');
        Storage::fake('s3');

        $this->artisan('migrate:fresh');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_backup_uploads_encrypted_artifact_and_logs_success(): void
    {
        Tenant::query()->create([
            'name' => 'Backup Beach',
            'slug' => 'backup-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
        ]);

        $log = app(DatabaseBackupService::class)->run();

        $this->assertSame(BackupLog::STATUS_COMPLETED, $log->status);
        $this->assertTrue($log->encrypted);
        $this->assertNotNull($log->remote_path);
        $this->assertNotNull($log->checksum);
        $this->assertGreaterThan(0, $log->size_bytes);

        Storage::disk('s3')->assertExists($log->remote_path);
        $this->assertDatabaseHas('backup_logs', [
            'id' => $log->id,
            'status' => BackupLog::STATUS_COMPLETED,
        ]);
    }

    public function test_real_restore_recovers_database_after_data_loss(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Restore Beach',
            'slug' => 'restore-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
        ]);

        $backupLog = app(DatabaseBackupService::class)->run();

        Tenant::query()->whereKey($tenant->id)->delete();
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);

        app(DatabaseRestoreService::class)->restore($backupLog, force: true);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'slug' => 'restore-beach',
            'name' => 'Restore Beach',
        ]);
    }

    public function test_failed_backup_records_failure(): void
    {
        config([
            'database.connections.sqlite.database' => storage_path('app/missing-database.sqlite'),
        ]);

        try {
            app(DatabaseBackupService::class)->run();
            $this->fail('Expected backup to fail for missing sqlite file.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseHas('backup_logs', [
            'status' => BackupLog::STATUS_FAILED,
            'driver' => 'sqlite',
        ]);
    }

    public function test_retention_removes_expired_backups_from_remote_storage(): void
    {
        Tenant::query()->create([
            'name' => 'Retention Beach',
            'slug' => 'retention-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
        ]);

        $log = app(DatabaseBackupService::class)->run();
        $log->forceFill(['created_at' => now()->subDays(60)])->save();

        config(['backup.retention_days' => 30]);

        $deleted = app(DatabaseBackupService::class)->cleanupExpired();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('backup_logs', ['id' => $log->id]);
        Storage::disk('s3')->assertMissing($log->remote_path);
    }

    public function test_artisan_commands_run_backup_restore_and_list(): void
    {
        Tenant::query()->create([
            'name' => 'CLI Beach',
            'slug' => 'cli-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
        ]);

        Artisan::call('backup:run');
        $this->assertStringContainsString('Backup completed', Artisan::output());

        $backupId = BackupLog::query()->where('status', BackupLog::STATUS_COMPLETED)->value('id');
        $this->assertNotNull($backupId);

        Artisan::call('backup:list');
        $this->assertStringContainsString('completed', Artisan::output());

        Tenant::query()->where('slug', 'cli-beach')->delete();

        Artisan::call('backup:restore', ['backup' => (string) $backupId, '--force' => true]);

        $this->assertDatabaseHas('tenants', ['slug' => 'cli-beach']);
    }
}
