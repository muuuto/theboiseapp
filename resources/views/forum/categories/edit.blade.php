<x-layout>
    <a href="/forum/category/manage" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>

    <x-card class="p-10 max-w-lg mx-auto mt-24">
        <header class="text-center">
            <h2 class="text-2xl font-bold uppercase mb-1">Edit your Category</h2>
            <p class="mb-4">Edit: {{$category->title}}</p>
        </header>

        <form method="POST" action="/forum/{{$category->id}}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label for="title" class="inline-block text-lg mb-2">Category Title</label>
                <input type="text" class="border border-gray-200 rounded p-2 w-full" name="title"
                    placeholder="Example: Memini, Liste, etc" value="{{$category->title}}" />

                @error('title')
                    <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="tags" class="inline-block text-lg mb-2">
                    Tags (Comma Separated - Separa con virgola)
                </label>
                <input type="text" class="border border-gray-200 rounded p-2 w-full" name="tags"
                    placeholder="Example: Memini, Guide, Vacanze, etc" value="{{$category->tags}}" />

                @error('tags')
                    <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="logo" class="inline-block text-lg mb-2">
                    Category Logo
                    <br>
                    (no need to reupload if you don't want to change the logo)
                </label>
                <input type="file" class="border border-gray-200 rounded p-2 w-full" accept="image/png, image/jpeg, image/webp" name="logo" value="{{$category->logo}}" />

                <img class="w-48 mr-6 mb-6"
                    src="{{$category->logo ? asset('storage/' . $category->logo) : asset('/images/no-image.png')}}" alt="" value="{{asset('storage/' . $category->logo)}}" />

                @error('logo')
                    <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="description" class="inline-block text-lg mb-2">
                    Category Description
                </label>
                <textarea class="border border-gray-200 rounded p-2 w-full" name="description" rows="10"
                    placeholder="Brief description of the category">{{$category->description}}
                </textarea>

                @error('description')
                    <p class="text-red-500 text-xs mt-1">{{$message}}</p>
                @enderror
            </div>

            <div class="mb-6">
                <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
                    Update Category
                </button>

                <a href="/forum" class="text-black ml-4"> Back </a>
            </div>
        </form>
    </x-card>
</x-layout>
