<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

// Lets admins run a fixed set of artisan commands, since the shared hosting has no SSH
class MaintenanceController extends Controller
{
    private const ACTIONS = [
        'clear-cache' => ['optimize:clear'],
        'backup' => ['backup:database'],
        'migrate' => ['backup:database', 'migrate --force'],
    ];

    public function index()
    {
        $migrator = app('migrator');
        $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);

        $backups = glob(config('backup.path') . '/theboise-*.sql.gz') ?: [];
        rsort($backups);

        // Before its migration runs the table doesn't exist: the page must still work to run it
        $invites = Schema::hasTable('invites')
            ? Invite::with(['creator', 'usedBy'])->latest()->limit(30)->get()
            : null;

        return view('maintenance.index', [
            'invites' => $invites,
            'pendingMigrations' => array_values($pending),
            'backups' => array_map(fn ($file) => [
                'name' => basename($file),
                'size' => round(filesize($file) / 1024),
            ], $backups),
            'phpVersion' => PHP_VERSION,
            'phpBinary' => PHP_BINARY,
            'laravelVersion' => app()->version(),
            'checks' => [
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'mbstring' => extension_loaded('mbstring'),
                'openssl' => extension_loaded('openssl'),
                'fileinfo' => extension_loaded('fileinfo'),
                'zlib (backups)' => extension_loaded('zlib'),
                'exif (photo rotation)' => extension_loaded('exif'),
                'gd with webp (image upload)' => function_exists('imagewebp'),
            ],
        ]);
    }

    public function run(string $action)
    {
        abort_unless(isset(self::ACTIONS[$action]), 404);

        $output = '';
        foreach (self::ACTIONS[$action] as $command) {
            $output .= '$ php artisan ' . $command . "\n";

            try {
                $exitCode = Artisan::call($command);
                $output .= Artisan::output() . "\n";
            } catch (\Throwable $e) {
                $exitCode = 1;
                $output .= 'ERROR: ' . $e->getMessage() . "\n";
            }

            // Never migrate if the backup before it failed
            if ($exitCode !== 0) {
                $output .= "Stopped: the command above failed.\n";
                break;
            }
        }

        Log::info('Maintenance action "' . $action . '" run by ' . auth()->user()->name, ['output' => $output]);

        return redirect('/admin/maintenance')->with('output', $output);
    }

    public function download(string $file)
    {
        abort_unless(preg_match('/^theboise-[0-9_-]+-[A-Za-z0-9]{16}\.sql\.gz$/', $file), 404);

        $path = config('backup.path') . '/' . $file;
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
