<x-layout>
  <a href="/" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>

  <x-card class="p-10 max-w-lg mx-auto mt-24">
    <header class="text-center">
      <h2 class="text-2xl font-bold uppercase mb-1">Create an Album</h2>
      <p class="mb-4">Post an album to store inside theboise.it</p>
    </header>

    <form method="POST" action="/listings" enctype="multipart/form-data">
      @csrf
      <div class="mb-6">
        <label for="dateFrom" class="inline-block text-lg mb-2">Date from</label>
        <input type="date" class="border border-gray-200 rounded p-2 w-full" name="dateFrom"
          value="{{old('dateFrom')}}" />

        @error('dateFrom')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="dateTo" class="inline-block text-lg mb-2">Date to</label>
        <input type="date" class="border border-gray-200 rounded p-2 w-full" name="dateTo"
          value="{{old('dateTo')}}" />

        @error('dateTo')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="title" class="inline-block text-lg mb-2">Album Title</label>
        <input type="text" class="border border-gray-200 rounded p-2 w-full" name="title"
          placeholder="Example: Vacanzeyney in Toscania" value="{{old('title')}}" />

        @error('title')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="location" class="inline-block text-lg mb-2">Album Location</label>
        <input type="text" class="border border-gray-200 rounded p-2 w-full" name="location"
          placeholder="Example: Terrazzina theboise, Pineta BZ>, etc" value="{{old('location')}}" />

        @error('location')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="albumLink" class="inline-block text-lg mb-2">
          Album URL
        </label>
        <input type="text" class="border border-gray-200 rounded p-2 w-full" name="albumLink"
          value="{{old('albumLink')}}" />

        @error('albumLink')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="tags" class="inline-block text-lg mb-2">
          Tags (Comma Separated)
        </label>
        <input type="text" class="border border-gray-200 rounded p-2 w-full" name="tags"
          placeholder="Example: Matteo, Zue, Anna, etc" value="{{old('tags')}}" />

        @error('tags')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
        <p>Anna, Matteo, Alessandro, Americani, Anj, Austin, Azzo, Beatrice, Bora, Canelupo, Cecio, Dario, Dave, Elena, Eleonora, Elian, Emilio, Federico, Feri, Filippo, Franzoso, Gallo, Gerta, Khalo, Lilli, Linda, Lorenzo, Luca, Mamma, Mamma Anna, Mariangela, Martin, Martina, Mary, Matt, Maya, Mikko, Mirko, Morelli, Murki, Nico, Omar, Quarty, Rudi, Sabine, Sara, Sonia, Sotto, Stella, Vale, Visi, Zecca, Zecca Piccolo</p>
      </div>

      <div class="mb-6">
        <label for="logo" class="inline-block text-lg mb-2">
          Album Preview
        </label>
        <input type="file" class="border border-gray-200 rounded p-2 w-full" accept="image/png, image/jpeg, image/webp" name="logo" />

        @error('logo')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
        <a href="https://bulkresizephotos.com/it?preset=true&type=exact&format=webp&quality=100" target=”_blank”>BulkResize</a>
      </div>

      <div class="mb-6">
        <label for="people" class="inline-block text-lg mb-2">
          People present in album
        </label>
        <select data-live-search="true" multiple="multiple" class="js-select2 form-control border border-gray-200 rounded p-2 w-full" name="people[]" id="people" >
          @foreach ($users as $user)
              <option value="{{$user->id}}" >
                {{$user->name}}
              </option>
          @endforeach
        </select>
        
        @error('people')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <label for="description" class="inline-block text-lg mb-2">
          Album Description
        </label>
        <textarea id="tinyMceTextArea" class="border border-gray-200 rounded p-2 w-full" name="description" rows="10"
          placeholder="Include what happened, why were you there, etc">{{old('description')}}</textarea>

        @error('description')
        <p class="text-red-500 text-xs mt-1">{{$message}}</p>
        @enderror
      </div>

      <div class="mb-6">
        <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
          Create Album
        </button>

        <a href="/" class="text-black ml-4"> Back </a>
      </div>
    </form>
  </x-card>
</x-layout>
