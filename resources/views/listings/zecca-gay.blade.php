@php
    $arrayOfYes = ["yes", "of course", "mega gay", "giga gay", "i hate him and he's gay", "definitely", "100% gay", "yes and also bald", "site working correctly, he's gay", "shibsha gay", "we both already know the answer", "yep", "aye", "yeah", "indeed", "undoubtedly", "unquestionably", "absolutely", "hell yeah"];
    $arrayKey = array_rand($arrayOfYes);
    $randomNum = rand(1,100);
    $randomNum <= 98 ? $isGay = $arrayOfYes[$arrayKey] : $isGay = "no, but the site might have some technical problem, wanna try again?";
@endphp

<x-layout>
    <x-card class="p-10 flex flex-col">
        <a href="/" class="inline-block text-black ml-4 mb-4"><i class="fa-solid fa-arrow-left"></i> Back
        </a>
      <header>
        <h1 class="text-3xl text-center font-bold my-6 uppercase">
          Is Zecca Gay??
        </h1>
      </header>
      
      <h2 class="text-2xl text-center my-6 uppercase">
        {{$isGay}}
      </h2>
      @if($randomNum > 98)
        <a class="lg:w-4/12 m-2 bg-laravel text-white rounded py-2 px-4 hover:bg-black text-center self-center" href="https://theboise.it/zeccaGay">Try again</a>
        <audio controls>
          <source src="{{asset('zue.mp3')}}" type="audio/mpeg"> 
        </audio>
      @endif
    </x-card>
  </x-layout>