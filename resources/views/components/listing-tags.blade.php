@props([
    'tagsCsv',
    'containVideo' => null,
])

@php
    $tags = explode(',', $tagsCsv);
    
    $videoLinks = !empty($containVideo);
@endphp

<ul class="flex flex-wrap">
    @if ($videoLinks)
        <li class="flex items-center justify-center bg-red text-white rounded-xl py-1 px-3 mr-2 mb-2 text-xs ">
            <a href="/?tag=Youtube">Youtube</a>
        </li>
    @endif
    @foreach ($tags as $tag)    
        <li class="flex items-center justify-center bg-black text-white rounded-xl py-1 px-3 mr-2 mb-2 text-xs ">
            <a href="/?tag={{$tag}}">{{$tag}}</a>
        </li>
    @endforeach
</ul>