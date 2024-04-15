<x-layout :category="$category">
  <a href="/forum/{{$category->id}}" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>

  <x-card class="p-10 max-w-lg mx-auto mt-24">
    <header class="text-center">
      <h2 class="text-2xl font-bold uppercase mb-1">Create a new Post</h2>
      <p class="mb-4">Create a new post inside the category {{$category->title}} inside the theboise.it forum</p>
    </header>
    
    <form method="POST" action="/forum/{{$category->id}}/store" enctype="multipart/form-data">
      @csrf

      <div class="mb-6">
        <label for="title" class="inline-block text-lg mb-2">Post Title</label>
        <input type="text" class="border border-gray-200 rounded p-2 w-full" name="title"
          placeholder="Example: Lista delle cose che odia Murki, Quella volta che, etc" value="{{old('title')}}" />

        @error('title')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="description" class="inline-block text-lg mb-2">
          Post brief description
        </label>
        <textarea class="border border-gray-200 rounded p-2 w-full" name="description" rows="10"
          placeholder="Brief description of the post">{{old('description')}}</textarea>

        @error('description')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="cover" class="inline-block text-lg mb-2">
          Post main image (cover)
        </label>
        <input type="file" class="border border-gray-200 rounded p-2 w-full" accept="image/png, image/jpeg, image/webp" name="cover" />

        @error('cover')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="tags" class="inline-block text-lg mb-2">
          Tags (Comma Separated - Separa con virgola)
        </label>
        <input type="text" class="border border-gray-200 rounded p-2 w-full" name="tags"
          placeholder="Example: Memini, Guide, Vacanze" value="{{old('tags')}}" />

        @error('tags')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="content" class="inline-block text-lg mb-2">
          Content of your post
        </label>
        <textarea id="tinyMceTextArea" class="border border-gray-200 rounded p-2 w-full" name="content" rows="20"
          placeholder="Write here the post">{{old('content')}}</textarea>

        @error('content')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="attachments" class="inline-block text-lg mb-2">
          Post attachments
        </label>
        
        <input type="file" multiple class="border border-gray-200 rounded p-2 w-full" accept="image/*" name="attachments[]" />

        @error('attachments')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6 flex">
        <label for="canEdit" class="inline-block text-lg mb-2">
          Others can edit your post
        </label>
        
        <input type="checkbox" class="border border-gray-200 p-2 rounded-full grow h-6" name="canEdit" />

        @error('canEdit')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6 flex">
        <label for="is_private" class="inline-block text-lg mb-2">
          Hide post from everybody 
          <br> 
          (to hide from specific users, see below)
        </label>
        
        <input type="checkbox" class="border border-gray-200 p-2 rounded-full grow h-6" name="is_private" />

        @error('is_private')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="hideFrom" class="inline-block text-lg mb-2">
          Hide this post from
        </label>
        <select data-live-search="true" multiple="multiple" class="js-select2 form-control border border-gray-200 rounded p-2 w-full" name="hideFrom[]" id="hideFrom" size="{{count($users)}}" >
          @foreach ($users as $user)
              <option value="{{$user->id}}" >
                {{$user->name}}
              </option>
          @endforeach
        </select>
        
        @error('hideFrom')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
          Create Post
        </button>

        <a href="/forum/{{$category->id}}" class="text-black ml-4"> Back </a>
      </div>
    </form>
  </x-card>
</x-layout>
