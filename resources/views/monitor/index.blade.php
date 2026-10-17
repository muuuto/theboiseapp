<x-layout>
    <a href="/admin/maintenance" class="ml-4 text-laravel hover:underline">
        <i class="fa-solid fa-screwdriver-wrench"></i> Maintenance
    </a>
    <div class="flex flex-col">
        @foreach ($users as $user)
            <div class="flex-row ml-4">
                {{$user->name}}    |    {{$user->last_login ?? 'no data'}}
            </div>
        @endforeach
    </div>
</x-layout>