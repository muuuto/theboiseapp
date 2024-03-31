@php
    $oldPeople = [];
    foreach($listing->users as $people) {
        array_push($oldPeople, $people->id);
    }
@endphp

<x-layout>
    <x-card class="p-10 max-w-lg mx-auto mt-24">
      <header class="text-center">
        <h2 class="text-2xl font-bold uppercase mb-1">Edit Album</h2>
        <p class="mb-4">Edit: {{$listing->title}}</p>
      </header>
  
      <form method="POST" action="/listings/{{$listing->id}}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="mb-6">
          <label for="dateFrom" class="inline-block text-lg mb-2">Date From</label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="dateFrom"
            value="{{$listing->dateFrom}}" />
  
          @error('dateFrom')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <label for="dateTo" class="inline-block text-lg mb-2">Date To</label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="dateTo"
            value="{{$listing->dateTo}}" />
  
          @error('dateTo')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>

        <div class="mb-6">
          <label for="title" class="inline-block text-lg mb-2">Album Title</label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="title"
            placeholder="Example: Vacanzeyney in Toscania" value="{{$listing->title}}" />
  
          @error('title')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <label for="location" class="inline-block text-lg mb-2">Album Location</label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="location"
            placeholder="Example: Terrazzina theboise, Pineta BZ" value="{{$listing->location}}" />
  
          @error('location')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <label for="albumLink" class="inline-block text-lg mb-2">
            Album URL
          </label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="albumLink"
            value="{{$listing->albumLink}}" />
  
          @error('albumLink')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <label for="tags" class="inline-block text-lg mb-2">
            Tags (Comma Separated)
          </label>
          <input type="text" class="border border-gray-200 rounded p-2 w-full" name="tags"
            placeholder="Example: Matteo, Zue, Anna, etc" value="{{$listing->tags}}" />
  
          @error('tags')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
          <p>Anna, Matteo, Alessandro, Americani, Anj, Austin, Azzo, Beatrice, Bora, Canelupo, Cecio, Dario, Dave, Elena, Eleonora, Elian, Emilio, Federico, Feri, Filippo, Franzoso, Gallo, Gerta, Khalo, Lilli, Linda, Lorenzo, Luca, Mamma, Mamma Anna, Martin, Mary, Matt, Maya, Mikko, Mirko, Morelli, Murki, Nico, Omar, Quarty, Rudi, Sabine, Sara, Sonia, Sotto, Stella, Vale, Visi, Zecca, Zecca Piccolo</p>
        </div>
  
        <div class="mb-6">
          <label for="logo" class="inline-block text-lg mb-2">
            Album Preview
          </label>
          <input type="file" class="border border-gray-200 rounded p-2 w-full" accept="image/png, image/jpeg, image/webp" name="logo" value="{{$listing->logo}}" />
  
          <img class="w-48 mr-6 mb-6"
            src="{{$listing->logo ? asset('storage/' . $listing->logo) : asset('/images/no-image.png')}}" alt="" value="{{asset('storage/' . $listing->logo)}}" />
  
          @error('logo')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <label for="people" class="inline-block text-lg mb-2">
            People present in album
          </label>
          
          <select data-live-search="true" multiple="multiple" class="js-select2 form-control border border-gray-200 rounded p-2 w-full" name="people[]" id="people" >
            @foreach ($users as $user)
                <option value="{{$user->id}}" @if (in_array($user->id, $oldPeople)) selected="selected" @style([
    'background-color: lightgreen']) @endif >
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
            placeholder="Include what happened, why were you there, etc">{{$listing->description}}</textarea>
  
          @error('description')
          <p class="text-red-500 text-xs mt-1">{{$message}}</p>
          @enderror
        </div>
  
        <div class="mb-6">
          <button class="bg-laravel text-white rounded py-2 px-4 hover:bg-black">
            Update Album
          </button>
  
          <a href="/" class="text-black ml-4"> Back </a>
        </div>
      </form>
    </x-card>
  </x-layout>
  