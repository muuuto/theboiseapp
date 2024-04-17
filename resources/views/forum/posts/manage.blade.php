<x-layout :category="$category" :posts="$posts">
  <a href="/forum" class="inline-block text-black ml-5"><i class="fa-solid fa-arrow-left"></i> Back </a>

  <x-card class="p-10">
    <header>
      <h1 class="text-3xl text-center font-bold my-6 uppercase">
        Manage your Post
      </h1>
    </header>

    <table class="w-full table-auto rounded-sm">
      <tbody>
        @unless(count($posts) == 0)
          @foreach($posts as $post)
            @if($post->is_private)
              @if($user->id == $post->author)
                <tr class="border-gray-300">
                  <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                    <a href="/forum/{{$category->id}}/{{$post->id}}"> {{$post->title}} </a>
                  </td>
                  <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                  <a href="/forum/{{$category->id}}/{{$post->id}}/edit" class="text-blue-400 px-6 py-2 rounded-xl"><i
                      class="fa-solid fa-pen-to-square"></i>
                      Edit</a>
                  </td>
                  <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                  <form method="POST" action="/forum/{{$category->id}}/{{$post->id}}">
                      @csrf
                      @method('DELETE')
                      <button class="text-red-500"><i class="fa-solid fa-trash"></i> Delete</button>
                  </form>
                  </td>
                </tr>
              @endif
            @else
              <tr class="border-gray-300">
                <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                  <a href="/forum/{{$category->id}}/{{$post->id}}"> {{$post->title}} </a>
                </td>
                <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                <a href="/forum/{{$category->id}}/{{$post->id}}/edit" class="text-blue-400 px-6 py-2 rounded-xl"><i
                    class="fa-solid fa-pen-to-square"></i>
                    Edit</a>
                </td>
                <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
                <form method="POST" action="/forum/{{$category->id}}/{{$post->id}}/delete">
                    @csrf
                    @method('DELETE')
                    <button class="text-red-500"><i class="fa-solid fa-trash"></i> Delete</button>
                </form>
                </td>
              </tr>
            @endif
          @endforeach
        @else
          <tr class="border-gray-300">
              <td class="px-4 py-8 border-t border-b border-gray-300 text-lg">
              <p class="text-center">No Posts Found</p>
              </td>
          </tr>
        @endunless
      </tbody>
    </table>
  </x-card>
</x-layout>
