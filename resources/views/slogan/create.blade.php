<x-layout>
    <a href="/" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>
    <x-card class="p-10 max-w-lg mx-auto mt-24">
      <header class="text-center">
        <h2 class="text-2xl font-bold uppercase mb-1">Create an Slogan</h2>
        <p class="mb-4">Post an slogan that will be shown in the homepage of theboise.it</p>
      </header>
  
      <form method="POST" action="/slogan">
        @csrf
  
        <div class="mb-6">
          <label for="sloganPhrase" class="inline-block text-lg mb-2">Slogan</label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="sloganPhrase"
            placeholder="Example: Zeccagay XD" value="{{old('sloganPhrase')}}" />
  
          @error('sloganPhrase')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
            Create Slogan
          </button>
        </div>
      </form>
    </x-card>
  </x-layout>
  