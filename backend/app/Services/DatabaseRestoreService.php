<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseRestoreService
{
    public function restore(BackupLog $backupLog, bool $force = false): void
    {
        if ($backupLog->status !== BackupLog::STATUS_COMPLETED) {
            throw new RuntimeException('Only completed backups can be restored.');
        }

        if (! $force && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Restore requires --force outside local/testing environments.');
        }

        if ($backupLog->driver !== config('database.default')) {
            throw new RuntimeException(
                "Backup driver [{$backupLog->driver}] does not match active connection [".config('database.default').'].'
            );
        }

        $localDisk = Storage::disk(config('backup.local_disk'));
        $localDisk->makeDirectory('restore');
        $artifactName = basename((string) $backupLog->remote_path);
        $localArtifact = 'restore/'.$artifactName;

        $remoteDisk = Storage::disk($backupLog->remote_disk ?: config('backup.remote_disk'));
        $remotePath = (string) $backupLog->remote_path;

        if (! $remoteDisk->exists($remotePath)) {
            throw new RuntimeException("Remote backup not found: {$remotePath}");
        }

        $stream = $remoteDisk->readStream($remotePath);

        if ($stream === false) {
            throw new RuntimeException("Unable to download remote backup: {$remotePath}");
        }

        $localDisk->writeStream($localArtifact, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $workingPath = $localArtifact;

        try {
            if ($backupLog->encrypted) {
                $workingPath = $this->decrypt($localDisk, $workingPath);
            }

            if (str_ends_with($workingPath, '.gz') || str_ends_with($workingPath, '.gz.enc')) {
                $workingPath = $this->decompress($localDisk, $workingPath);
            }

            $this->importDump($backupLog->driver, $localDisk->path($workingPath));
        } finally {
            foreach ($localDisk->files('restore') as $file) {
                $localDisk->delete($file);
            }
        }
    }

    protected function decrypt($localDisk, string $encryptedPath): string
    {
        $decryptedPath = preg_replace('/\.enc$/', '', $encryptedPath) ?: $encryptedPath;
        $payload = $localDisk->get($encryptedPath);
        $localDisk->put($decryptedPath, Crypt::decryptString($payload));

        return $decryptedPath;
    }

    protected function decompress($localDisk, string $compressedPath): string
    {
        $decompressedPath = preg_replace('/\.gz(\.enc)?$/', '', $compressedPath) ?: $compressedPath;
        $input = gzopen($localDisk->path($compressedPath), 'rb');

        if ($input === false) {
            throw new RuntimeException("Unable to read compressed backup: {$compressedPath}");
        }

        $output = fopen($localDisk->path($decompressedPath), 'wb');

        if ($output === false) {
            gzclose($input);
            throw new RuntimeException("Unable to write decompressed backup: {$decompressedPath}");
        }

        while (! gzeof($input)) {
            $chunk = gzread($input, 1024 * 512);

            if ($chunk === false) {
                break;
            }

            fwrite($output, $chunk);
        }

        gzclose($input);
        fclose($output);

        return $decompressedPath;
    }

    protected function importDump(string $driver, string $absolutePath): void
    {
        match ($driver) {
            'sqlite' => $this->restoreSqlite($absolutePath),
            'pgsql' => $this->restorePostgres($absolutePath),
            'mysql', 'mariadb' => $this->restoreMysql($driver, $absolutePath),
            default => throw new RuntimeException("Unsupported database driver for restore: {$driver}"),
        };
    }

    protected function restoreSqlite(string $absolutePath): void
    {
        $databasePath = config('database.connections.sqlite.database');

        if ($databasePath === ':memory:') {
            throw new RuntimeException('Cannot restore into an in-memory SQLite database.');
        }

        DB::disconnect('sqlite');

        if (! copy($absolutePath, $databasePath)) {
            throw new RuntimeException('Failed to restore SQLite database file.');
        }

        DB::reconnect('sqlite');
    }

    protected function restorePostgres(string $absolutePath): void
    {
        $connection = config('database.connections.pgsql');

        $command = [
            'pg_restore',
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-acl',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--username='.$connection['username'],
            '--dbname='.$connection['database'],
            $absolutePath,
        ];

        $environment = ['PGPASSWORD' => (string) $connection['password']];
        $result = Process::timeout(600)->env($environment)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'pg_restore failed.');
        }
    }

    protected function restoreMysql(string $driver, string $absolutePath): void
    {
        $connection = config("database.connections.{$driver}");

        $command = [
            'mysql',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            $connection['database'],
        ];

        $environment = [];

        if (($connection['password'] ?? '') !== '') {
            $environment['MYSQL_PWD'] = (string) $connection['password'];
        }

        $result = Process::timeout(600)
            ->env($environment)
            ->input(file_get_contents($absolutePath) ?: '')
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'mysql restore failed.');
        }
    }
}
