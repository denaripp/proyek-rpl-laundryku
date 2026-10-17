<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Tahap 21 — Database backup via mysqldump.
 *
 * Usage:
 *   php artisan db:backup                          # default timestamped file
 *   php artisan db:backup --output=path/to/file    # custom path
 *
 * Configuration via config/database.php (connections.mysql) — same as app.
 * MySQL credentials read from .env (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD).
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup {--output= : Output file path} {--keep=7 : Number of recent backups to keep in storage/app/backups}';

    protected $description = 'Backup MySQL database via mysqldump. Stores in storage/app/backups/.';

    public function handle(): int
    {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');

        if (!$database || !$username) {
            $this->error('DB_DATABASE / DB_USERNAME not set. Configure .env first.');
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $filename = $this->option('output')
            ?: $backupDir . '/backup-' . $database . '-' . now()->format('Ymd-His') . '.sql';

        // Write credentials to a temp file (safer than passing via CLI).
        $defaultsFile = tempnam(sys_get_temp_dir(), 'mysql_creds_');
        file_put_contents($defaultsFile, sprintf(
            "[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%s\n",
            $username,
            addslashes($password),
            $host,
            $port
        ));

        $cmd = sprintf(
            'mysqldump --defaults-file=%s --single-transaction --routines --triggers %s > %s 2>/dev/null',
            escapeshellarg($defaultsFile),
            escapeshellarg($database),
            escapeshellarg($filename)
        );

        $this->info("Running: mysqldump {$database} → {$filename}");
        exec($cmd, $output, $returnCode);
        @unlink($defaultsFile);

        if ($returnCode !== 0) {
            $this->error("mysqldump failed (exit {$returnCode}). Check mysqldump is on PATH.");
            return self::FAILURE;
        }

        $size = filesize($filename);
        $this->info(sprintf('OK: %s (%.2f MB)', $filename, $size / 1024 / 1024));

        // Prune old backups (keep last N).
        $keep = (int) $this->option('keep');
        if ($keep > 0) {
            $files = collect(File::files($backupDir))
                ->sortByDesc(fn($f) => $f->getMTime())
                ->slice($keep);
            foreach ($files as $f) {
                File::delete($f->getPathname());
                $this->warn("Pruned old backup: " . $f->getFilename());
            }
        }

        return self::SUCCESS;
    }
}