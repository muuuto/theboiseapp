<x-layout>
    @include('partials._hero')

    <div class="flex flex-col">
        @unless (count($categories) == 0)
            @foreach ($categories as $category)
                <x-categories-card :category="$category" :user="$user" />
            @endforeach
        @else
            <p>No Listing found</p>
        @endunless
    </div>
</x-layout>