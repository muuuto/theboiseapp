@php $counter = 0 @endphp
@props(['category', 'user'])

<x-card class="grow mb-4">
    <div class="flex flex-row flex-nowrap items-center overflow-hidden">
        <a href="/forum/{{$category->id}}">
            <img
                class="min-w-fit flex-col basis-1/5"
                src="{{$category->logo ? asset('storage/app/public/' . $category->logo) : asset('/images/no-image.png')}}"
                alt=""
            />
            <div class="flex flex-col flex-wrap basis-3/5">
                <div class="ml-8">
                    <h3 class="text-2xl font-bold">
                        <div>{{$category->title}}</div>
                    </h3>
                    
                    <div class="text-xl mb-2 overflow-hidden">{{$category->description}}</div>
            
                    <x-forum-tags :tagsCsv="$category->tags"></x-forum-tags>
                </div>
            </div>
            <div class="flex grow text-center items-end flex-col basis-1/5">
                <div class="hidden">
                    @foreach($category->posts()->get() as $post)
                        @if($post->is_private)
                            @if($user->id == $post->author)
                                {{++$counter}}
                            @endif
                        @else
                            {{++$counter}}
                        @endif
                    @endforeach
                </div>
                {{$counter}} 
                <br>
                post
            </div>
        </a>
    </div>
</x-card>
