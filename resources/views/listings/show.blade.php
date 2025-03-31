<x-layout>
    @include('partials._search')

    <a href="/" class="inline-block text-black ml-4 mb-4"><i class="fa-solid fa-arrow-left"></i> Back
    </a>
    <div class="mx-4">
        <x-card class="p-10">
            <div class="flex flex-col items-center justify-center text-center">
                <img class="lg:w-8/12 mb-6" src="{{$listing->logo ? asset('storage/app/public/' . $listing->logo) : asset('/public/images/no-image.png')}}" alt="" />

                <h3 class="text-2xl mb-2">{{$listing->title}}</h3>
                <div class="text-xl font-bold mb-4">{{date('l j F, Y', strtotime($listing->dateFrom))}} - {{date('l j F, Y', strtotime($listing->dateTo))}}</div>
                <x-listing-tags :tagsCsv="$listing->tags"></x-listing-tags>
                <div class="text-lg my-4">
                    <i class="fa-solid fa-location-dot"></i> {{$listing->location}}
                </div>
                <div class="border border-gray-200 w-full mb-6"></div>
                <div>
                    <h3 class="text-3xl font-bold mb-4">
                        Album Description
                    </h3>
                    <div class="text-lg space-y-6">
                        <div class="text-left">
                            {!!$listing->description!!}
                        </div>
                        <br>
                        <a href="{{$listing->albumLink}}" target="_blank" class="block bg-black text-white py-2 rounded-xl hover:opacity-80"><i class="fa-solid fa-globe"></i> Visit
                            Album</a>
                        @if(count($videoLinks) > 0)
                            @foreach ($videoLinks as $videoLink)
                                <a href="{{$videoLink}}" target="_blank" class="block bg-red-700 text-white py-2 rounded-xl hover:opacity-80"><i class="fa-solid fa-globe"></i> Go to video {{ $loop->iteration }}</a>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="border border-gray-200 w-full mb-6"></div>
                <h3 class="text-2xl font-bold mb-6">
                    People in this album
                </h3>
                @if(count($listing->users) < 6)
                    <div class="lg:grid lg:grid-cols-{{count($listing->users)}} gap-4 w-full grid grid-cols-2 space-y-4 md:space-y-0 mx-4">
                @else
                    <div class="lg:grid lg:grid-cols-6 gap-4 grid grid-cols-2 w-full space-y-4 md:space-y-0 mx-4">
                @endif
                    @unless (count($listing->users) == 0)
                        @foreach ($listing->users as $user)
                            <div class="flex-column flex-warp text-center justify-self-center self-end">
                                <img
                                    class="md:block max-w-24 m-auto mb-4"
                                    src="{{$user->profilePicture ? asset('storage/app/public/' . $user->profilePicture) : asset('/images/user.webp')}}"
                                    alt=""
                                />
                                <div class="m-auto md:block mb-4">
                                    {{$user->name}}
                                </div>
                            </div>
                        @endforeach
                    @endunless
                </div>
            </div>
        </x-card>

        @if(auth()->user() ? auth()->user()->getAttribute('isAdmin') == 1 : null)
            <x-card class="mt-4 p-2 flex space-x-6">
                <a href="/listings/{{$listing->id}}/edit">
                    <i class="fa-solid fa-pencil"></i> Edit
                </a>

                <form method="POST" action="/listings/{{$listing->id}}">
                    @csrf
                    @method('DELETE')
                    <button class="text-red-500"><i class="fa-solid fa-trash"></i> Delete</button>
                </form>
                <div x-data="{ input: '{{$listing->albumLink}}', showMsg: false }" >
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
            <div class ="mx-4" x-data="{ input: '{{$listing->albumLink}}', showMsg: false }" >
                <div class="w-full overflow-hidden">
                    <a type="button" @click="navigator.clipboard.writeText(input), showMsg = true, setTimeout(() => showMsg = false, 1000)">
                        <i class="fa fa-share" aria-hidden="true"></i> <button id="clipboard">Share</button>
                        <div x-show="showMsg" @click.away="showMsg = false" class="fixed bottom-3 right-3 z-20 max-w-sm overflow-hidden bg-green-100 border border-green-300 rounded" style="display: none;">
                            <p class="p-3 flex items-center justify-center text-green-600">Copied to Clipboard</p>
                        </div>
                    </a>
                </div>
            </div>
        @endif
        <div class="mx-4">
            <h2>Comments</h2>
            <div>
                <form method="POST" action="/listings/comments">
                    @csrf
                    <div class="mb-6 flex flex-col">
                        <input type="hidden" name="user_id" value="{{$user->id}}">
                        <input type="hidden" name="listing_id" value="{{$listing->id}}">
                        <textarea id="tinyMceTextArea" class="m-2 border border-gray-200 rounded p-2 w-full" name="comment"
                            placeholder="Example: my favorite photo is.." value="{{old('comment')}}">{{old('description')}}</textarea>
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
                                    {!! $comment->comment !!} XD
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
    </div>
</x-layout>
