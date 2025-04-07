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
        @if(auth()->user() && auth()->user()->id == 28)
            <title>TheBoise | Find your album</title>
            <meta name="description" content="TheBoise: more than 100 albums, visit the forum and much more.">
        @else
            <title>TheBoise | Find your album XD</title>
            <meta name="description" content="TheBoise: more than 100 albumses, find out if zecca is truly gay and visit the forumses.">
        @endif
    </head>
    <body class="mb-24 bg-slate-100">
        <nav class="sticky top-0 z-20 bg-slate-100 flex justify-between items-center">
            <a href="/"
                ><img class="w-24" src="{{asset('images/logo.png')}}" alt="" class="logo"
            /></a>
            <ul class="flex space-x-6 mr-6 text-lg">
                @auth
                <li class="ml-2">
                    <a href="/account">
                        <i class="text-teal-600 hover:text-laravel fa-solid fa-user"></i>
                        <span class="font-bold uppercase text-teal-600 hover:text-laravel">
                            Hi {{auth()->user()->name}}
                        </span>
                    </a>
                </li>
                <li class="text-center">
                    <form class="inline" method="POST" action="/logout">
                    @csrf
                    <button class="hover:text-laravel" type="submit">
                        <i class="fa-solid fa-door-closed"></i> Logout
                    </button>
                    </form>
                </li>
                @if(auth()->user() ? auth()->user()->getAttribute('isAdmin') == 1 : null)
                    {{-- <li>
                        <a href="/listings/manage" class="hover:text-laravel"><i class="fa-solid fa-gear"></i> Manage Listings</a>
                    </li> --}}
                    <li class="text-center">
                        <a href="/monitor" class="text-laravel"><i class="fa-solid fa-shield"></i> Monitor</a>
                    </li>
                @endif
                <li class="text-center">
                    <a href="/forum" class="hover:text-laravel text-green-600"><i class="fa-solid fa-folder"></i> Forum</a>
                </li>
                <li class="text-center">
                    <div id="notificationButton" class="relative cursor-pointer group">
                        <div class="hover:text-laravel">
                            <i class="relative fa-solid fa-bell hover:text-laravel">
                                @if ($unseenListings->count() || $anniversaryNotification->count())
                                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
                                @endif
                            </i>
                            New
                        </div>
                        <!-- Dropdown -->
                        <div id="notificationDropdown"
                             class="hidden absolute right-0 mt-2 w-96 bg-white shadow-lg rounded-lg z-50">
                            <div class="p-2">
                                @forelse ($anniversaryNotification as $item)
                                    <div class="p-2 hover:text-laravel">
                                        <a href="https://theboise.it/listings/{{$item->id}}">
                                            {{$item->diffInYears}} years ago: <strong>{{ $item->title }}</strong>
                                        </a>
                                    </div>
                                    @if (!$loop->last)
                                        <hr>
                                    @endif
                                @empty
                                    <div class="p-2 text-gray-500">No memories today</div>
                                @endforelse
                                @forelse ($unseenListings as $item)
                                    <div class="p-2 hover:text-laravel">
                                        <a href="https://theboise.it/listings/{{$item->id}}">
                                            New album: <strong>{{ $item->title }}</strong>
                                        </a>
                                    </div>
                                    @if (!$loop->last)
                                        <hr>
                                    @endif
                                @empty
                                    <div class="p-2 text-gray-500">No new album</div>
                                @endforelse
                            </div>
                        </div>
                    </div>                    
                </li>
                @else
                <li class="text-center">
                    <a href="/register" class="hover:text-laravel"><i class="fa-solid fa-user-plus"></i> Register</a>
                </li>
                <li>
                    <a href="/login" class="hover:text-laravel"><i class="fa-solid fa-arrow-right-to-bracket"></i> Login</a>
                </li>
                @endauth
            </ul>
        </nav>

        <main>
            {{$slot}}
        </main>

        @if(auth()->user())
            <footer class="fixed z-10 bottom-0 left-0 w-full flex flex-row font-bold bg-teal-600 text-white h-16 mt-4 justify-between">
                @if(Route::is('forum.categories.*'))
                    <a
                        href="/forum/category/manage"
                        class="bg-black rounded-xl text-white py-2 px-5 self-center ml-9"
                        >Manage Categories</a
                    >
                @elseif(Route::is('forum.posts.*') )
                    <a
                        href="/forum/{{$category->id}}/manage"
                        class="bg-black rounded-xl text-white py-2 px-5 self-center ml-9"
                        >Manage Posts</a
                    >
                @else
                    @if(auth()->user()->id != 28)
                        <a
                            href="/slogan/create"
                            class="bg-black rounded-xl text-white py-2 px-5 self-center ml-9"
                            >Post Slogan</a
                        >
                    @endif
                @endif

                @if(Route::is('forum.categories.*') )
                    <a
                        href="/forum/category/create"
                        class="bg-black rounded-xl text-white py-2 px-5 self-center mr-9"
                        >Create Category</a
                    >
                @elseif(Route::is('forum.posts.*'))
                    <a
                        href="/forum/{{$category->id}}/create"
                        class="bg-black rounded-xl text-white py-2 px-5 self-center mr-9"
                        >New Post</a
                    >
                @elseif(auth()->user() ? auth()->user()->getAttribute('isAdmin') == 1 : null)
                    <a
                        href="/listings/create"
                        class="bg-black rounded-xl text-white py-2 px-5 self-center mr-9"
                        >Post Album</a
                    >
                @endif
                {{-- <p class="lg:absolute relative top-2/3 text-white text-center">Copyright &copy; 2023, All Rights reserved</p> --}}
            </footer>
            @endif
        <x-flash-message />
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const btn = document.getElementById('notificationButton');
                const dropdown = document.getElementById('notificationDropdown');
        
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('hidden');
                });
        
                // Hide dropdown if clicking outside
                document.addEventListener('click', function (e) {
                    if (!dropdown.classList.contains('hidden')) {
                        dropdown.classList.add('hidden');
                    }
                });
            });
        </script>
    </body>
</html>
