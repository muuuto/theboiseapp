<p>Hello {{ $user->name }},</p>

<p>You have the following albums to view:</p>
@foreach ($listings as $listing)
    <strong>{{ $listing->title }}</strong>
    
    <p>
        <a href="https://theboise.it/listings/{{ $listing->id }}"
           style="display: inline-block; padding: 10px 20px; background-color: #3490dc; color: white; text-decoration: none; border-radius: 5px;">
            View Album
        </a>
    </p>
    <br>
@endforeach

<p>Thanks,<br> theboise.it team</p>
