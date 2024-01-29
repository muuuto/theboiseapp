<x-layout>
    <div class="flex flex-col">
        @foreach ($users as $user)
            <div class="flex-row ml-4">
                {{$user->name}}    |    {{$user->last_login ?? 'no data'}}
            </div>
        @endforeach
    </div>
</x-layout>