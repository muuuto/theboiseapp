<x-layout :category="$category">
    @include('partials._hero')
    {{-- @include('partials._searchForum') --}}

    <a href="/forum" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>

    <div class="flex flex-col">
        @unless (count($posts) == 0)
            @foreach ($posts as $post)  
                @if($post->is_private)
                    @if($user->id == $post->author)
                        <x-posts-card :category="$category" :post="$post" />
                    @endif
                @else
                    <x-posts-card :category="$category" :post="$post" />
                @endif
            @endforeach
        @else
            <p class="ml-6 mt-6">No post inside this category yet, create one!</p>
        @endunless
    </div>
</x-layout>