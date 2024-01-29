@props(['tagsCsv'])

@php
    $tags = explode(',', $tagsCsv);
@endphp

<ul class="flex flex-wrap">
    @foreach ($tags as $tag)    
        <li class="flex items-center justify-center bg-black text-white rounded-xl py-1 px-3 mr-2 mb-2 text-xs ">
            <a>{{$tag}}</a>
        </li>
    @endforeach
</ul>