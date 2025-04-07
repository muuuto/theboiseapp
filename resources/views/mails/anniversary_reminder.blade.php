<p>Hello {{ $user->name }},</p>

@foreach ($listings as $listing)
    <p>On this day, <strong>{{$listing->diffInYears}}</strong> years ago, you were here: <strong>{{ $listing->title }}</strong> ({{$listing->dateFrom}} - {{$listing->dateTo}})</p>
    
    <img src="{{asset('storage/app/public/' . $listing->logo)}}" width="800" height="600" alt="">
    <p>
        <a href="https://theboise.it/listings/{{ $listing->id }}"
           style="display: inline-block; padding: 10px 20px; background-color: #3490dc; color: white; text-decoration: none; border-radius: 5px;">
            View Album
        </a>
    </p>
    <br>
@endforeach

<p>Thanks,<br> theboise.it team</p>
