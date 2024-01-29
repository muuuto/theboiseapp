@props(['category', 'post'])

<x-card class="grow mb-4">
    <div class="flex flex-row flex-nowrap items-center overflow-hidden">
        <a href="/forum/{{$category->id}}/{{$post->id}}">
            @if ($post->cover)
                <img
                    class="lg:w-48 w-36 flex-col object-cover max-h-48 basis-1/5"
                    src="{{asset('storage/app/public/' . $post->cover)}}"
                    alt=""
                />
            @endif
            <div class="flex flex-col flex-wrap {{$post->cover ? 'basis-3/5' : 'basis-4/5'}}">
                <div class="ml-8">
                    <h3 class="text-2xl font-bold">
                        <div>{{$post->title}}</div>
                    </h3>

                    <div class="text-xl mb-2 overflow-hidden">{{$post->description}}</div>
                    
                    <div>Author: {{$post->author()->getResults()->name}}</div>
            
                    <div class="text-lg mb-2">
                        {{date('j F, Y', strtotime($post->created_at))}}
                    </div>
                    <x-forum-tags :tagsCsv="$post->tags"></x-forum-tags>
                </div>
            </div>
            <div class="flex grow text-center items-end flex-col basis-1/5">
                <div class="text-end"> 
                    {{$post->counter}} <i class="fa-solid fa-eye"></i> 
                    <br>
                    {{$post->comments()->count()}} <i class="fa-solid fa-comment"></i>
                </div>
            </div>
        </a>
    </div>
</x-card>
