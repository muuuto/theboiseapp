<x-layout :category="$category" :post="$post">
    <a href="/forum/{{$category->id}}" class="inline-block text-black ml-4 mb-4"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <div class="mx-4">
        <x-card class="p-10">
            <div class="flex flex-col items-center justify-center">
                @if($post->cover)
                    <img class="lg:w-8/12 lg:mr-6 mb-6" src="{{asset('storage/app/public/' . $post->cover)}}" alt="" />
                @endif
                <h1 class="text-3xl mb-2 font-bold">{{$post->title}}</h1>
                <h2 class="text-2xl mb-2">Author: {{$author}}</h2>

                <div class="border border-gray-200 w-full mb-6"></div>
                <div class="w-full">
                    <div class="text-lg space-y-6">
                        {!! $post->content !!}
                        <br>
                    </div>
                </div>

                @if($imageAttachments)
                    <br>
                    <h3 class="text-xl mb-2">Image attachments:</h3>
                    <div class="w-8/12 flex flew-row items-center justify-center">
                        <br>
                        @foreach ($imageAttachments as $key => $attachment)
                            <a href="{{asset('storage/app/public/' . $attachment)}}">
                                <img class="lg:w-8/12 lg:mr-6 mb-6" src="{{asset('storage/app/public/' . $attachment)}}" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            @if($pdfAttachments)
                <br>
                <h3 class="text-xl mb-2">Pdf attachments:</h3>
                
                <div class="w-8/12 flex flew-row">
                    @foreach ($pdfAttachments as $key => $attachment)
                        <br>
                        <a href="{{asset('storage/app/public/' . $attachment)}}" target="_blank">{{$attachment}}</a>
                    @endforeach
                </div>
            @endif

            @if(auth()->user()->id == $post->author()->getResults()->id)
                <x-card class="mt-4 p-2 flex space-x-6">
                    <a href="/forum/{{$category->id}}/{{$post->id}}/edit">
                        <i class="fa-solid fa-pencil"></i> Edit post
                    </a>
                    <form method="POST" action="/forum/{{$category->id}}/{{$post->id}}">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-500"><i class="fa-solid fa-trash"></i> Delete Post</button>
                    </form>

                    <div x-data="{ input: '{{url()->full()}}', showMsg: false }" > 
                        <div class="w-full overflow-hidden">
                            <a type="button" @click="navigator.clipboard.writeText(input), showMsg = true, setTimeout(() => showMsg = false, 1000)">
                                <i class="fa fa-share" aria-hidden="true"></i> <button id="clipboard">Share</button>
                                <div x-show="showMsg" @click.away="showMsg = false" class="fixed bottom-3 right-3 z-20 max-w-sm overflow-hidden bg-green-100 border border-green-300 rounded" style="display: none;">
                                    <p class="p-3 flex items-center justify-center text-green-600">Copied to Clipboard</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </x-card>
            @elseif($post->canEdit)
                <x-card class="mt-4 p-2 flex space-x-6">
                    <a href="/forum/{{$category->id}}/{{$post->id}}/edit">
                        <i class="fa-solid fa-pencil"></i> Edit post
                    </a>
                    <div x-data="{ input: '{{url()->full()}}', showMsg: false }" > 
                        <div class="w-full overflow-hidden">
                            <a type="button" @click="navigator.clipboard.writeText(input), showMsg = true, setTimeout(() => showMsg = false, 1000)">
                                <i class="fa fa-share" aria-hidden="true"></i> <button id="clipboard">Share</button>
                                <div x-show="showMsg" @click.away="showMsg = false" class="fixed bottom-3 right-3 z-20 max-w-sm overflow-hidden bg-green-100 border border-green-300 rounded" style="display: none;">
                                    <p class="p-3 flex items-center justify-center text-green-600">Copied to Clipboard</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </x-card>
            @else
                <x-card class="mt-4 p-2 flex space-x-6">
                    <div x-data="{ input: '{{url()->full()}}', showMsg: false }" > 
                        <div class="w-full overflow-hidden">
                            <a type="button" @click="navigator.clipboard.writeText(input), showMsg = true, setTimeout(() => showMsg = false, 1000)">
                                <i class="fa fa-share" aria-hidden="true"></i> <button id="clipboard">Share</button>
                                <div x-show="showMsg" @click.away="showMsg = false" class="fixed bottom-3 right-3 z-20 max-w-sm overflow-hidden bg-green-100 border border-green-300 rounded" style="display: none;">
                                    <p class="p-3 flex items-center justify-center text-green-600">Copied to Clipboard</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </x-card>
            @endif
            
            <div class="mx-2">
                <h2>Comments</h2>
                <div>
                    <form method="POST" action="/forum/{{$category->id}}/{{$post->id}}/comment">
                        @csrf
                        <div class="mb-6 flex flex-col items-center justify-center">
                            <input type="hidden" name="user_id" value="{{$user->id}}">
                            <input type="text" class="m-2 border border-gray-200 rounded p-2 w-full" name="comment"
                                placeholder="Example: qifsha ropt.." value="{{old('comment')}}" />
                            <button class="lg:w-4/12 m-2 bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                                Add Comment
                            </button>
                        </div>
                    </form>
                </div>
                <div>
                    @unless (count($comments) == 0)
                        @foreach ($comments as $comment)
                            <div class="flex flex-row">
                                <div class="self-center min-w-fit">
                                    <img
                                    class="w-16 max-h-16 mr-6 md:block rounded-full items-center object-cover"
                                    src="{{$comment->user->profilePicture ? asset('storage/app/public/' . $comment->user->profilePicture) : asset('/images/user.webp')}}"
                                    alt=""
                                    />                            
                                </div>
                                <div>
                                    <div class="font-bold">
                                        {{$comment->user->name}} 
                                    </div>
                                    <p>
                                        {{$comment->comment}} XD
                                    </p>
                                    <div class="text-slate-400">
                                        {{date('j F, Y h:i:s A', strtotime($comment->created_at))}}
                                    </div>
                                </div>
                            </div>
                            <br>
                        @endforeach
                    @else
                        <p>No comments found, be the first</p>
                    @endunless
                </div>
            </div>

        </x-card>
    </div>
</x-layout>