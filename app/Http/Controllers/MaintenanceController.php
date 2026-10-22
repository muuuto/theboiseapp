<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\AlbumEvent;
use App\Models\Listing;
use App\Models\User;
use App\Support\AlbumHistory;
use App\Support\Settings;
use App\Support\Media;
use App\Support\MailSamples;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
        // One-time jobs of the album redesign (safe to run again: they skip what's already done)
        'trim-album-covers' => ['albums:trim-cover-bars'],
        'import-album-coordinates' => ['albums:import-coordinates'],
    ];

    public function index()
    {
        $migrator = app('migrator');
        $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);

        $backups = glob(config('backup.path') . '/theboise-*.sql.gz') ?: [];
        rsort($backups);

        // Before its migration runs the table doesn't exist: the page must still work to run it
        // Only what the page shows: everything given to a Vue page reaches the browser
        $invites = Schema::hasTable('invites')
            ? Invite::with(['creator', 'usedBy'])->latest()->limit(30)->get()->map(fn (Invite $invite) => [
                'id' => $invite->id,
                'note' => $invite->note,
                'usedBy' => $invite->used_at ? ($invite->usedBy?->name ?? 'deleted user') : null,
                'usedAt' => $invite->used_at?->toIso8601String(),
                'expiresAt' => $invite->expires_at->toIso8601String(),
                'expired' => $invite->expires_at->isPast(),
            ])
            : null;

        return Inertia::render('Admin/Maintenance', [
            'invites' => $invites,
            'pendingMigrations' => array_values($pending),
            'backups' => array_map(fn ($file) => [
                'name' => basename($file),
                'size' => round(filesize($file) / 1024),
            ], $backups),
            'mailTemplates' => MailSamples::TEMPLATES,
            'albumPosting' => $this->albumPosting(),
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

    // Members posting albums: the site switch, who's allowed, and the album history.
    // null until its migration runs
    private function albumPosting(): ?array
    {
        if (! Schema::hasTable('album_events') || ! Schema::hasColumn('users', 'can_post_albums')) {
            return null;
        }

        $posted = Listing::selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return [
            'enabled' => (bool) Settings::get(Settings::MEMBERS_CAN_POST_ALBUMS, false),
            'people' => User::where('is_demo', false)->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => Media::url($user->profilePicture),
                'isAdmin' => $user->isAdmin == 1,
                'canPost' => (bool) $user->can_post_albums,
                'albumsPosted' => $posted[$user->id] ?? 0,
            ]),
            'history' => AlbumEvent::with('user')->latest('id')->limit(40)->get()->map(fn ($event) => AlbumHistory::describe($event)),
        ];
    }

    public function albumPostingMode(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        Settings::set(Settings::MEMBERS_CAN_POST_ALBUMS, (bool) $data['enabled']);
        AlbumEvent::record($data['enabled'] ? 'posting_on' : 'posting_off');

        return back()->with('message', $data['enabled'] ? 'Members can post albums (the ones you allow)' : 'Only admins can post albums');
    }

    public function albumPostingPermission(Request $request, User $user)
    {
        $data = $request->validate(['canPost' => 'required|boolean']);
        abort_if($user->is_demo, 422, 'The demo account never posts albums');

        if ((bool) $user->can_post_albums !== (bool) $data['canPost']) {
            $user->update(['can_post_albums' => $data['canPost']]);
            AlbumEvent::record($data['canPost'] ? 'permission_granted' : 'permission_revoked', null, ['user' => $user->name]);
        }

        return back();
    }

    // Sends one email with sample data to the admin, to see how it looks
    public function testEmail(Request $request)
    {
        $data = $request->validate(['template' => 'required|in:' . implode(',', array_keys(MailSamples::TEMPLATES))]);
        $admin = $request->user();

        try {
            MailSamples::send($data['template'], $admin->email, $admin);
        } catch (\Throwable $e) {
            Log::error('Test email failed', ['template' => $data['template'], 'error' => $e->getMessage()]);

            return back()->withErrors(['template' => 'The email could not be sent: ' . $e->getMessage()]);
        }

        return back()->with('message', 'Test email "' . MailSamples::TEMPLATES[$data['template']] . '" sent to ' . $admin->email);
    }

    public function download(string $file)
    {
        abort_unless(preg_match('/^theboise-[0-9_-]+-[A-Za-z0-9]{16}\.sql\.gz$/', $file), 404);

        $path = config('backup.path') . '/' . $file;
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
