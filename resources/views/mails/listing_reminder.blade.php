<p>Hello {{ $user->name }},</p>

<p>You have a listing titled <strong>{{ $listing->title }}</strong> waiting to be viewed.</p>

<p>
    <a href="{{ route('listing.show', $listing) }}"
       style="display: inline-block; padding: 10px 20px; background-color: #3490dc; color: white; text-decoration: none; border-radius: 5px;">
        View Listing
    </a>
</p>

<p>Thanks,<br>{{ config('app.name') }}</p>
