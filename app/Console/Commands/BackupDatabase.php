<?php

namespace App\Console\Commands;

use App\Console\Mail\BackupMail;
use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--mail : Also email the backup to BACKUP_MAIL_TO}';
    protected $description = 'Dump the database to a gzipped SQL file and remove old backups';

    public function handle()
    {
        $path = config('backup.path');
        File::ensureDirectoryExists($path);

        // In case the storage folder is ever reachable from the web: deny access and use unguessable names
        File::put($path . '/.htaccess', "Require all denied\n");

        $db = config('database.connections.' . config('database.default'));
        $file = $path . '/theboise-' . now()->format('Y-m-d_His') . '-' . Str::random(16) . '.sql.gz';

        // Pure PHP dump: shared hosting often blocks exec(), so we can't call the mysqldump binary
        $dump = new Mysqldump(
            "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']}",
            $db['username'],
            $db['password'],
            [
                'compress' => 'Gzip',
                'add-drop-table' => true,
                'single-transaction' => true,
                'lock-tables' => false,
                'default-character-set' => 'utf8mb4',
            ]
        );
        $dump->start($file);

        $this->info('Backup written to ' . $file . ' (' . round(filesize($file) / 1024) . ' KB)');

        $this->pruneOldBackups($path);

        if ($this->option('mail')) {
            if (config('backup.mail_to')) {
                Mail::to(config('backup.mail_to'))->send(new BackupMail($file));
                $this->info('Backup emailed to ' . config('backup.mail_to'));
            } else {
                $this->warn('BACKUP_MAIL_TO is not set, backup not emailed.');
            }
        }

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $path): void
    {
        $files = glob($path . '/theboise-*.sql.gz') ?: [];
        sort($files);

        foreach (array_slice($files, 0, max(0, count($files) - config('backup.keep'))) as $old) {
            File::delete($old);
        }
    }
}
