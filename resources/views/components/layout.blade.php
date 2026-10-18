{{-- Layout of the pages not converted to Vue yet: same sidebar/drawer as resources/js/Layouts/AppLayout.vue --}}
@php
    $isDemo = auth()->user()?->is_demo;
    $isAdmin = auth()->user()?->isAdmin == 1;
    $navLink = 'nav-item relative flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition-colors';
    $navIdle = 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
    $navActive = 'bg-red-50 text-laravel';
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="title" content="TheBoise.it" />
        <link rel="icon" href="{{asset('images/logo.png')}}" />
        <x-head.tinymce-config/>
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"
            integrity="sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g=="
            crossorigin="anonymous"
            referrerpolicy="no-referrer"
        />
        {{-- Collapsed sidebar (desktop): applied before drawing, same choice as the Vue pages --}}
        <script>
            try {
                if (localStorage.getItem('sidebar-collapsed') === '1') document.documentElement.classList.add('sidebar-collapsed');
            } catch (e) {}
        </script>
        <style>
            .app-sidebar, .app-content { transition: width .2s, padding .2s; }
            .nav-tip { display: none; }
            @media (min-width: 1024px) {
                .sidebar-collapsed .app-sidebar { width: 5rem; }
                .sidebar-collapsed .app-content { padding-left: 5rem; }
                .sidebar-collapsed .app-sidebar .nav-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
                .sidebar-collapsed .app-sidebar .nav-item { justify-content: center; }
                .sidebar-collapsed .app-sidebar .nav-head { justify-content: center; padding: 0; }
                .sidebar-collapsed .app-sidebar .nav-hide { display: none; }
                .sidebar-collapsed .app-sidebar nav { overflow: visible; }
                .sidebar-collapsed .nav-tip {
                    display: block; position: absolute; left: 100%; margin-left: .75rem; z-index: 50; pointer-events: none;
                    white-space: nowrap; border-radius: .5rem; background: #0f172a; color: #fff; padding: .25rem .625rem;
                    font-size: .75rem; opacity: 0; transition: opacity .15s;
                }
                .sidebar-collapsed .nav-item:hover .nav-tip { opacity: 1; }
                .sidebar-collapsed .collapse-icon { transform: rotate(180deg); }
            }
        </style>
        <script src="//unpkg.com/alpinejs" defer></script>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: "class",
                theme: {
                    extend: {
                        colors: {
                            laravel: "#ef3b2d",
                        },
                    },
                },
            };
        </script>
        @if($isDemo || ! auth()->check())
            <title>TheBoise | Find your album</title>
            <meta name="description" content="TheBoise: a private library of photo albums and memories.">
        @else
            <title>TheBoise | Find your album XD</title>
            <meta name="description" content="TheBoise: more than 100 albumses, find out if zecca is truly gay and visit the forumses.">
        @endif
    </head>
    <body class="bg-slate-100 text-slate-800" x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
        @auth
        {{-- Drawer backdrop (phones) --}}
        <div x-show="drawer" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="drawer = false" style="display: none"></div>

        {{-- Sidebar: always visible on large screens, a drawer on phones --}}
        <aside class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
            :class="drawer ? 'translate-x-0' : '-translate-x-full'">
            <div class="nav-head flex h-16 items-center justify-between px-5">
                <a href="/" class="flex items-center gap-2">
                    <img src="{{asset('images/logo.png')}}" alt="" class="h-9 w-auto" />
                    <span class="nav-hide text-lg font-extrabold uppercase tracking-tight">The<span class="text-laravel">Boise</span></span>
                </a>
                <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 lg:hidden" @click="drawer = false">
                    <i class="fa-solid fa-xmark text-lg"></i><span class="sr-only">Close menu</span>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <a href="/" class="{{ $navLink }} {{ request()->is('/') ? $navActive : $navIdle }}">
                    <i class="fa-solid fa-images w-5 text-center"></i> <span class="nav-label">Albums</span><span class="nav-tip">Albums</span>
                </a>
                <a href="/map" class="{{ $navLink }} {{ request()->is('map') ? $navActive : $navIdle }}">
                    <i class="fa-solid fa-map-location-dot w-5 text-center"></i> <span class="nav-label">Map</span><span class="nav-tip">Map</span>
                </a>
                @unless ($isDemo)
                <a href="/forum" class="{{ $navLink }} {{ request()->is('forum*') ? $navActive : $navIdle }}">
                    <i class="fa-solid fa-comments w-5 text-center"></i> <span class="nav-label">Forum</span><span class="nav-tip">Forum</span>
                </a>
                @endunless
                <a href="/account" class="{{ $navLink }} {{ request()->is('account*') ? $navActive : $navIdle }}">
                    <i class="fa-solid fa-user w-5 text-center"></i> <span class="nav-label">Account</span><span class="nav-tip">Account</span>
                </a>

                @if ($isAdmin)
                    <p class="nav-hide px-3 pt-6 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Admin</p>
                    <a href="/listings/create" class="{{ $navLink }} {{ request()->is('listings/create') ? $navActive : $navIdle }}">
                        <i class="fa-solid fa-circle-plus w-5 text-center"></i> <span class="nav-label">Post album</span><span class="nav-tip">Post album</span>
                    </a>
                    <a href="/monitor" class="{{ $navLink }} {{ request()->is('monitor') ? $navActive : $navIdle }}">
                        <i class="fa-solid fa-desktop w-5 text-center"></i> <span class="nav-label">Monitor</span><span class="nav-tip">Monitor</span>
                    </a>
                    <a href="/admin/maintenance" class="{{ $navLink }} {{ request()->is('admin/*') ? $navActive : $navIdle }}">
                        <i class="fa-solid fa-screwdriver-wrench w-5 text-center"></i> <span class="nav-label">Maintenance</span><span class="nav-tip">Maintenance</span>
                    </a>
                @endif
            </nav>

            <div class="border-t border-slate-200 p-3">
                <button type="button" class="{{ $navLink }} {{ $navIdle }} w-full max-lg:hidden"
                    @click="document.documentElement.classList.toggle('sidebar-collapsed'); try { localStorage.setItem('sidebar-collapsed', document.documentElement.classList.contains('sidebar-collapsed') ? '1' : '0') } catch (e) {}">
                    <i class="collapse-icon fa-solid fa-angles-left w-5 text-center"></i> <span class="nav-label">Collapse</span><span class="nav-tip">Expand</span>
                </button>
                <form method="POST" action="/logout">
                    @csrf
                    <button class="{{ $navLink }} {{ $navIdle }} w-full hover:text-laravel" type="submit">
                        <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> <span class="nav-label">Logout</span><span class="nav-tip">Logout</span>
                    </button>
                </form>
            </div>
        </aside>
        @endauth

        <div class="{{ auth()->check() ? 'app-content lg:pl-64' : '' }}">
            {{-- Top bar --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6">
                @auth
                <button class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="drawer = true">
                    <i class="fa-solid fa-bars text-lg"></i><span class="sr-only">Open menu</span>
                </button>
                @endauth
                <a href="/" class="{{ auth()->check() ? 'lg:hidden' : '' }}">
                    <img src="{{asset('images/logo.png')}}" alt="TheBoise" class="h-8 w-auto" />
                </a>

                <div class="ml-auto flex items-center gap-1">
                    @auth
                    {{-- Notifications: memories of the day and albums not seen yet --}}
                    @php($unreadLikes = collect($likeNotifications)->contains('read', false))
                    {{-- Opening the bell marks the likes as read --}}
                    <div class="relative" x-data="{ open: false, unread: @js($unreadLikes) }" @click.outside="open = false">
                        <button class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                            @click="open = !open; if (open && unread) { unread = false; fetch('/notifications/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()) } }) }">
                            <i class="fa-solid fa-bell text-lg"></i>
                            @if (($unseenListings->count()) || (!empty($anniversaryNotification)) || $unreadLikes)
                                <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-laravel ring-2 ring-white"></span>
                            @endif
                            <span class="sr-only">Notifications</span>
                        </button>
                        <div x-show="open" x-transition style="display: none"
                            class="absolute right-0 mt-2 max-h-[75vh] w-80 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl">
                            @if (count($likeNotifications))
                            <div class="border-b border-slate-100 p-2">
                                <h3 class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Likes</h3>
                                @foreach ($likeNotifications as $like)
                                    <a href="{{ $like['url'] }}" class="flex items-start gap-3 rounded-xl px-3 py-2 text-sm hover:bg-slate-100 {{ $like['read'] ? '' : 'bg-red-50' }}">
                                        <i class="fa-solid fa-heart mt-0.5 text-laravel"></i>
                                        <span class="min-w-0"><strong>{{ $like['liker'] }}</strong> liked your comment
                                            <span class="block truncate text-xs text-slate-500">"{{ $like['excerpt'] }}" · {{ $like['title'] }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                            @endif
                            <div class="p-2">
                                <h3 class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">On this day</h3>
                                @forelse ($anniversaryNotification as $item)
                                    <a href="/listings/{{$item->id}}" class="flex items-start gap-3 rounded-xl px-3 py-2 text-sm hover:bg-slate-100">
                                        <i class="fa-solid fa-calendar-days mt-0.5 text-laravel"></i>
                                        <span>{{$item->diffInYears}} {{ $item->diffInYears == 1 ? 'year' : 'years' }} ago: <strong>{{ $item->title }}</strong></span>
                                    </a>
                                @empty
                                    <p class="px-3 py-2 text-sm text-slate-400">No memories today</p>
                                @endforelse
                            </div>
                            <div class="border-t border-slate-100 p-2">
                                <h3 class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">New albums</h3>
                                @forelse ($unseenListings as $item)
                                    <a href="/listings/{{$item->id}}" class="flex items-start gap-3 rounded-xl px-3 py-2 text-sm hover:bg-slate-100">
                                        <i class="fa-solid fa-image mt-0.5 text-teal-600"></i>
                                        <strong>{{ $item->title }}</strong>
                                    </a>
                                @empty
                                    <p class="px-3 py-2 text-sm text-slate-400">No new albums</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <a href="/account" class="ml-2 flex items-center gap-2 rounded-full py-1 pr-1 pl-3 hover:bg-slate-100">
                        <span class="hidden text-sm font-semibold text-teal-600 sm:inline">Hi {{auth()->user()->name}}</span>
                        @if (auth()->user()->profilePicture)
                            <img src="{{ \App\Support\Media::url(auth()->user()->profilePicture) }}" alt="" class="h-8 w-8 rounded-full object-cover" />
                        @else
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-600 text-sm font-bold text-white">
                                {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        @endif
                    </a>
                    @else
                    <a href="/login" class="rounded-xl px-3 py-2 font-medium hover:bg-slate-100 hover:text-laravel">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
                    </a>
                    @endauth
                </div>
            </header>

            <main class="py-4">
                {{$slot}}
            </main>
        </div>

        <x-flash-message />
    </body>
</html>
