@props(['listing'])

<x-card>
    <a href="/listings/{{$listing->id}}">
        <div class="flex flex-column flex-wrap">
            <img
                class="w-full w-48 mr-6 md:block"
                src="{{$listing->logo ? asset('storage/app/public/' . $listing->logo) : asset('/images/no-image.png')}}"
                alt=""
            />
            <div>
                <h3 class="text-2xl">
                    <div>{{$listing->title}}</div>
                </h3>
                
                <div class="text-xl font-bold mb-4">{{date('j F, Y', strtotime($listing->dateFrom))}} - {{date('j F, Y', strtotime($listing->dateTo))}}</div>

                <x-listing-tags :tagsCsv="$listing->tags"></x-listing-tags>
                <div class="text-lg mt-4">
                    <i class="fa-solid fa-location-dot"></i> {{$listing->location}}
                </div>
            </div>
        </div>
    </a>
</x-card>