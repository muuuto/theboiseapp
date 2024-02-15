<x-layout>
    <a href="/" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>
    <x-card class="p-10 max-w-lg mx-auto mt-24">
        <div class="flex flex-col items-center justify-center text-center">
            <img
                class="w-48 md:block mb-6"
                src="{{$user->profilePicture ? asset('storage/app/public/' . $user->profilePicture) : asset('/images/user.webp')}}"
                alt=""
            />
            <h1 class="text-3xl font-bold mb-4">
                Hello {{$user->name}}
            </h1>
            <p class="mb-4">Here you can customize your profile inside theboise.it</p>
        </div>
        <form method="POST" action="/account/update" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="mb-6">
                <label for="name" class="inline-block text-lg mb-2">Username</label>
                <input type="text" class="border border-gray-200 rounded p-2 w-full" name="name"
                    value="{{$user->name}}" />
        
                @error('name')
                <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>
            <div class="mb-6">
                <label for="profilePicture" class="inline-block text-lg mb-2">Profile picture</label>
                <input type="file" class="border border-gray-200 rounded p-2 w-full" name="profilePicture"
                accept="capture=camera; image/*" />
        
                @error('profilePicture')
                    <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>
            <div class="mb-6">
                <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black" type="submit">
                    Update Profile
                </button>
            </div>
        </form>
        <br>
        <form method="POST" action="/account/addWallet" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="flex flex-col items-center justify-center text-center mt-16">
                <h2 class="text-3xl font-bold mb-4">
                    Your wallet
                </h2>
                <p class="mb-4">Here you can add personal media to your profile</p>
                <p class="mb-4">The current maximum media upload limit is 1. Uploading a new media will replace the old one</p>
            </div>
            <div class="mb-6">
                <label for="mediaName" class="inline-block text-lg mb-2">Name of media</label>
                <input type="text" class="border border-gray-200 rounded p-2 w-full" name="mediaName" />
        
                @error('mediaName')
                <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>
            <div class="mb-6">
                <label for="addWallet" class="inline-block text-lg mb-2">Media upload</label>
                <input type="file" class="border border-gray-200 rounded p-2 w-full" name="addWallet"
                accept="capture=camera; image/*" />
        
                @error('addWallet')
                    <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>
            <div class="mb-6">
                <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black" type="submit">
                    Add media
                </button>
            </div>
        </form>

        <div class="w-full">
            @if($walletName)
                <div class="flex flex-wrap border-gray-300">
                    <img class="mt-6" src="{{asset('storage/app/public/' . $walletMedia)}}" />
                    <h3 class="flex-1 text-center text-2xl mt-2">{{$walletName}}</h3>
                </div>
            @else
                <div class="border-gray-300">
                    <p class="text-center">No media associated</p>
                </div>
            @endunless
        </div>
    </x-card>
</x-layout>