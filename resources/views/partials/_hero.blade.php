<section class="relative h-78 bg-teal-600 flex flex-col justify-center align-center text-center space-y-4 mb-4">
    <div class="absolute top-0 left-0 w-full h-full opacity-50 bg-repeat bg-contain"
        style="background-image: url('images/protruding-squares.png')">
    </div>

    <div class="z-10">
        <h1 class="text-6xl font-bold uppercase text-white">
            The<span class="text-black">Boise</span>
        </h1>
        <p class="text-2xl text-gray-200 font-bold my-4">
            @if (auth()->user()->id == 28)
                Welcome to THEBOISE.IT
            @else
                {!! $slogan["sloganPhrase"] !!} - by {{$slogan["author"]}}
            @endif
        </p>
        @if (auth()->user()->id != 28)
            <div>
                <a href="/zeccaGay"
                    class="inline-block my-2 border-2 border-white text-white py-2 px-4 rounded-xl uppercase mt-2 hover:text-black hover:border-black"
                >Is Zecca gay?</a>
            </div>
            <div>
                <a href="https://paypal.me/zeccasupergay"
                    class="inline-block  my-4 border-2 border-white text-white py-2 px-4 rounded-xl uppercase mt-2 hover:text-black hover:border-black"
                >Offer me a sprissino</a>
            </div>
            @else
            <div>
                <a href="https://theboise.it/forum"
                class="inline-block  my-4 border-2 border-white text-white py-2 px-4 rounded-xl uppercase mt-2 hover:text-black hover:border-black"
                >Visit our Forum</a>
            </div>
            <div>
                <a href="https://github.com/muuuto/theboiseapp.git"
                    class="inline-block  my-4 border-2 border-white text-white py-2 px-4 rounded-xl uppercase mt-2 hover:text-black hover:border-black"
                >Source code of the project</a>
            </div>
        @endif
    </div>
</section>