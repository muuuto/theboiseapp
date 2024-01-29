<x-layout :category="$category">
    <a href="/forum" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>

    <x-card class="p-10">
      <header>
        <h1 class="text-3xl text-center font-bold my-6 uppercase">
          Manage your Categories
        </h1>
      </header>

      <table class="w-full table-auto rounded-sm">
        <tbody>

          @unless($category->count() == 0)
            @foreach($category as $categorySingle)
            <tr class="border-gray-300">
                <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                <a href="/forum/{{$categorySingle->id}}"> {{$categorySingle->title}} </a>
                </td>
                <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                <a href="/forum/{{$categorySingle->id}}/edit" class="text-blue-400 px-6 py-2 rounded-xl"><i
                    class="fa-solid fa-pen-to-square"></i>
                    Edit</a>
                </td>
                @if(auth()->user() ? auth()->user()->getAttribute('isAdmin') == 1 : null)
                  <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                  <form method="POST" action="/forum/{{$categorySingle->id}}">
                      @csrf
                      @method('DELETE')
                      <button class="text-red-500"><i class="fa-solid fa-trash"></i> Delete</button>
                  </form>
                  </td>
                @endif
            </tr>
            @endforeach
          @else
            <tr class="border-gray-300">
                <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                <p class="text-center">You have no category to manage</p>
                </td>
            </tr>
          @endunless
        </tbody>
      </table>
    </x-card>
  </x-layout>
  