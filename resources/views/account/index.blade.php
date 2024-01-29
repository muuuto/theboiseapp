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
    </x-card>
</x-layout>