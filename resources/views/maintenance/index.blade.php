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
            <h2 class="text-xl font-bold mb-2">One-time album jobs</h2>
            <p class="text-sm mb-3">
                Safe to run more than once: they skip what's already done. The import never undoes the review:
                it only adds the missing extra places (e.g. Dublin next to London) to albums that still have
                one of the suggested places, and never brings back a place you removed.
            </p>
            <div class="flex flex-wrap gap-4 items-center">
                <form method="POST" action="/admin/maintenance/trim-album-covers">
                    @csrf
                    <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                        <i class="fa-solid fa-crop-simple"></i> Remove bars from old album previews
                    </button>
                </form>
                <form method="POST" action="/admin/maintenance/import-album-coordinates">
                    @csrf
                    <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                        <i class="fa-solid fa-location-dot"></i> Import suggested album locations
                    </button>
                </form>
                <a href="/admin/locations" class="text-laravel hover:underline">
                    <i class="fa-solid fa-map-location-dot"></i> Review album locations
                </a>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold mb-2">Invites</h2>

            @if (is_null($invites))
                <p>Run the pending migrations to enable invites.</p>
            @else
                @if (session('inviteLink'))
                    <div class="bg-green-100 border border-green-300 rounded p-4 mb-4"
                        x-data="{ copied: false }">
                        <p class="mb-2">New invite link. Copy it now: it won't be shown again.</p>
                        <input type="text" readonly class="border border-gray-200 rounded p-2 w-full font-mono text-sm"
                            value="{{ session('inviteLink') }}" x-ref="link" />
                        <button type="button" class="mt-2 bg-laravel text-white rounded py-1 px-3 hover:bg-black"
                            @click="navigator.clipboard.writeText($refs.link.value); copied = true">
                            <i class="fa-solid fa-copy"></i> <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                        </button>
                    </div>
                @endif

                <form method="POST" action="/admin/invites" class="flex flex-wrap gap-2 items-end mb-4">
                    @csrf
                    <div>
                        <label for="note" class="block text-sm">For (optional)</label>
                        <input type="text" name="note" id="note" maxlength="255" placeholder="Example: Zecca"
                            class="border border-gray-200 rounded p-2" />
                    </div>
                    <div>
                        <label for="days" class="block text-sm">Valid for</label>
                        <select name="days" id="days" class="border border-gray-200 rounded p-2">
                            <option value="1">1 day</option>
                            <option value="7" selected>7 days</option>
                            <option value="30">30 days</option>
                        </select>
                    </div>
                    <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                        <i class="fa-solid fa-user-plus"></i> Create invite link
                    </button>
                </form>

                @forelse ($invites as $invite)
                    <div class="text-sm flex flex-wrap gap-2 items-center">
                        <span>{{ $invite->note ?: 'No note' }}</span>
                        @if ($invite->used_at)
                            <span class="text-green-600">
                                used by {{ $invite->usedBy?->name ?? 'deleted user' }} on {{ $invite->used_at->format('d/m/Y') }}
                            </span>
                        @elseif ($invite->expires_at->isPast())
                            <span class="text-gray-500">expired {{ $invite->expires_at->format('d/m/Y') }}</span>
                        @else
                            <span>open until {{ $invite->expires_at->format('d/m/Y H:i') }}</span>
                            <form method="POST" action="/admin/invites/{{ $invite->id }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">revoke</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p>No invites yet.</p>
                @endforelse
            @endif
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
