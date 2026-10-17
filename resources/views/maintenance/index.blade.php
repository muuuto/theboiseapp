<x-layout>
    <div class="mx-4 mb-8 space-y-6">
        <h1 class="text-2xl font-bold uppercase">Maintenance</h1>
        <p class="text-sm">PHP {{ $phpVersion }} &middot; Laravel {{ $laravelVersion }}</p>
        <p class="text-sm font-mono">{{ $phpBinary }}</p>

        <div class="flex flex-wrap gap-x-4 text-sm">
            @foreach ($checks as $name => $ok)
                <span class="{{ $ok ? 'text-green-600' : 'text-red-600 font-bold' }}">
                    <i class="fa-solid {{ $ok ? 'fa-check' : 'fa-xmark' }}"></i> {{ $name }}
                </span>
            @endforeach
        </div>

        @if (session('output'))
            <pre class="bg-black text-green-400 p-4 rounded overflow-x-auto text-sm">{{ session('output') }}</pre>
        @endif

        <div class="flex flex-wrap gap-4">
            <form method="POST" action="/admin/maintenance/clear-cache">
                @csrf
                <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                    <i class="fa-solid fa-broom"></i> Clear caches
                </button>
            </form>

            <form method="POST" action="/admin/maintenance/backup">
                @csrf
                <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                    <i class="fa-solid fa-database"></i> Backup database now
                </button>
            </form>

            <form method="POST" action="/admin/maintenance/migrate">
                @csrf
                <button class="bg-red-600 text-white rounded py-2 px-4 hover:bg-black disabled:opacity-40" @disabled(empty($pendingMigrations))>
                    <i class="fa-solid fa-play"></i> Backup + run migrations
                </button>
            </form>
        </div>

        <div>
            <h2 class="text-xl font-bold mb-2">Pending migrations</h2>
            @forelse ($pendingMigrations as $migration)
                <div class="font-mono text-sm">{{ $migration }}</div>
            @empty
                <p>None, the database is up to date.</p>
            @endforelse
        </div>

        <div>
            <h2 class="text-xl font-bold mb-2">Backups</h2>
            @forelse ($backups as $backup)
                <div class="text-sm">
                    <a class="text-laravel hover:underline" href="/admin/maintenance/backups/{{ $backup['name'] }}">
                        <i class="fa-solid fa-download"></i> {{ $backup['name'] }}
                    </a>
                    ({{ $backup['size'] }} KB)
                </div>
            @empty
                <p>No backups yet.</p>
            @endforelse
        </div>
    </div>
</x-layout>
