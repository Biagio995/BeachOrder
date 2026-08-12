<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Services\Monitoring\BackupMonitor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseBackupService
{
    public function __construct(
        protected BackupAlertService $alertService,
    ) {}

    public function run(): BackupLog
    {
        $driver = config('database.default');
        $timestamp = now()->format('Y-m-d_His');
        $baseName = "{$driver}_{$timestamp}.sql";

        $localDisk = Storage::disk(config('backup.local_disk'));
        $stagingPaths = [];
        $backupLog = null;

        try {
            $dumpPath = $this->createDump($driver, $baseName);
            $stagingPaths[] = $dumpPath;

            $backupLog = BackupLog::query()->create([
                'filename' => $baseName,
                'driver' => $driver,
                'status' => BackupLog::STATUS_RUNNING,
                'encrypted' => (bool) config('backup.encrypt'),
                'started_at' => now(),
            ]);

            $compressedPath = $this->compress($localDisk, $dumpPath);
            $stagingPaths[] = $compressedPath;

            $artifactPath = config('backup.encrypt')
                ? $this->encrypt($localDisk, $compressedPath)
                : $compressedPath;

            if ($artifactPath !== $compressedPath) {
                $stagingPaths[] = $artifactPath;
            }

            $remotePath = $this->uploadToRemote($artifactPath);
            $checksum = hash('sha256', $localDisk->get($artifactPath));

            $backupLog->update([
                'filename' => basename($artifactPath),
                'status' => BackupLog::STATUS_COMPLETED,
                'remote_disk' => config('backup.remote_disk'),
                'remote_path' => $remotePath,
                'size_bytes' => $localDisk->size($artifactPath),
                'checksum' => $checksum,
                'completed_at' => now(),
            ]);

            Log::info('Database backup completed', [
                'backup_log_id' => $backupLog->id,
                'remote_path' => $remotePath,
                'size_bytes' => $backupLog->size_bytes,
            ]);

            BackupMonitor::recordSuccess($remotePath, (int) $backupLog->size_bytes);

            return $backupLog->fresh();
        } catch (\Throwable $exception) {
            if ($backupLog) {
                $backupLog->update([
                    'status' => BackupLog::STATUS_FAILED,
                    'error_message' => $exception->getMessage(),
                    'completed_at' => now(),
                ]);

                $this->alertService->alertFailure($backupLog, $exception);
            } else {
                $failedLog = BackupLog::query()->create([
                    'filename' => $baseName,
                    'driver' => $driver,
                    'status' => BackupLog::STATUS_FAILED,
                    'encrypted' => (bool) config('backup.encrypt'),
                    'error_message' => $exception->getMessage(),
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);

                $this->alertService->alertFailure($failedLog, $exception);
            }

            BackupMonitor::recordFailure($exception->getMessage());

            throw $exception;
        } finally {
            foreach ($stagingPaths as $path) {
                if ($localDisk->exists($path)) {
                    $localDisk->delete($path);
                }
            }
        }
    }

    public function cleanupExpired(): int
    {
        $retentionDays = (int) config('backup.retention_days');

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;
        $remoteDisk = Storage::disk(config('backup.remote_disk'));
        $prefix = trim((string) config('backup.remote_path_prefix'), '/');

        BackupLog::query()
            ->where('status', BackupLog::STATUS_COMPLETED)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (BackupLog $log) use (&$deleted, $remoteDisk): void {
                if ($log->remote_path && $remoteDisk->exists($log->remote_path)) {
                    $remoteDisk->delete($log->remote_path);
                }

                $log->delete();
                $deleted++;
            });

        if ($deleted > 0) {
            Log::info('Expired database backups removed', ['count' => $deleted]);
        }

        return $deleted;
    }

    protected function createDump(string $driver, string $baseName): string
    {
        return match ($driver) {
            'sqlite' => $this->dumpSqlite($baseName),
            'pgsql' => $this->dumpPostgres($baseName),
            'mysql', 'mariadb' => $this->dumpMysql($driver, $baseName),
            default => throw new RuntimeException("Unsupported database driver for backup: {$driver}"),
        };
    }

    protected function dumpSqlite(string $baseName): string
    {
        $databasePath = config('database.connections.sqlite.database');

        if ($databasePath === ':memory:') {
            throw new RuntimeException('Cannot backup an in-memory SQLite database.');
        }

        if (! is_string($databasePath) || ! file_exists($databasePath)) {
            throw new RuntimeException("SQLite database file not found: {$databasePath}");
        }

        $relativePath = 'staging/'.Str::beforeLast($baseName, '.').'.sqlite';
        $localDisk = Storage::disk(config('backup.local_disk'));
        $localDisk->makeDirectory('staging');

        $destination = $localDisk->path($relativePath);

        if (! copy($databasePath, $destination)) {
            throw new RuntimeException('Failed to copy SQLite database file.');
        }

        return $relativePath;
    }

    protected function dumpPostgres(string $baseName): string
    {
        $connection = config('database.connections.pgsql');
        $relativePath = 'staging/'.Str::beforeLast($baseName, '.').'.dump';
        $localDisk = Storage::disk(config('backup.local_disk'));
        $localDisk->makeDirectory('staging');
        $destination = $localDisk->path($relativePath);

        $command = [
            'pg_dump',
            '--format=custom',
            '--no-owner',
            '--no-acl',
            '--file='.$destination,
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--username='.$connection['username'],
            $connection['database'],
        ];

        $environment = ['PGPASSWORD' => (string) $connection['password']];
        $result = Process::timeout(600)->env($environment)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'pg_dump failed.');
        }

        return $relativePath;
    }

    protected function dumpMysql(string $driver, string $baseName): string
    {
        $connection = config("database.connections.{$driver}");
        $relativePath = 'staging/'.Str::beforeLast($baseName, '.').'.sql';
        $localDisk = Storage::disk(config('backup.local_disk'));
        $localDisk->makeDirectory('staging');
        $destination = $localDisk->path($relativePath);

        $command = [
            'mysqldump',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--result-file='.$destination,
            $connection['database'],
        ];

        $environment = [];

        if (($connection['password'] ?? '') !== '') {
            $environment['MYSQL_PWD'] = (string) $connection['password'];
        }

        $result = Process::timeout(600)->env($environment)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'mysqldump failed.');
        }

        return $relativePath;
    }

    protected function compress($localDisk, string $sourcePath): string
    {
        $compressedPath = $sourcePath.'.gz';
        $source = $localDisk->path($sourcePath);
        $destination = $localDisk->path($compressedPath);

        $input = fopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException("Unable to read backup file: {$sourcePath}");
        }

        $output = gzopen($destination, 'wb9');

        if ($output === false) {
            fclose($input);
            throw new RuntimeException("Unable to write compressed backup: {$compressedPath}");
        }

        while (! feof($input)) {
            $chunk = fread($input, 1024 * 512);

            if ($chunk === false) {
                break;
            }

            gzwrite($output, $chunk);
        }

        fclose($input);
        gzclose($output);

        return $compressedPath;
    }

    protected function encrypt($localDisk, string $sourcePath): string
    {
        $encryptedPath = $sourcePath.'.enc';
        $payload = Crypt::encryptString($localDisk->get($sourcePath));
        $localDisk->put($encryptedPath, $payload);

        return $encryptedPath;
    }

    protected function uploadToRemote(string $localRelativePath): string
    {
        $remoteDiskName = config('backup.remote_disk');
        $remoteDisk = Storage::disk($remoteDiskName);
        $prefix = trim((string) config('backup.remote_path_prefix'), '/');
        $remotePath = $prefix.'/'.basename($localRelativePath);
        $localDisk = Storage::disk(config('backup.local_disk'));
        $stream = $localDisk->readStream($localRelativePath);

        if ($stream === false) {
            throw new RuntimeException("Unable to read local backup artifact: {$localRelativePath}");
        }

        $uploaded = $remoteDisk->writeStream($remotePath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($uploaded === false) {
            throw new RuntimeException("Failed to upload backup to remote disk [{$remoteDiskName}].");
        }

        return $remotePath;
    }
}
