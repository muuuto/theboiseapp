<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Listing;
use App\Models\Slogan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Comment;
use App\Models\ListingUser;
use App\Support\AlbumCover;
use App\Support\Media;
use App\Support\SafeHtml;
use Inertia\Inertia;

class ListingController extends Controller
{
    // Show all listings (the albums the user is in)
    public function index()
    {
        $user = auth()->user();
        $filters = request()->only(['tag', 'search']);

        $albums = $user->listing()
            ->withPivot('seen')
            ->filter($filters)
            ->orderBy('dateFrom', 'desc')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Listing $listing) => [
                'id' => $listing->id,
                'title' => $listing->title,
                'cover' => Media::url($listing->logo) ?? asset('images/no-image.png'),
                'dateFrom' => $listing->dateFrom,
                'dateTo' => $listing->dateTo,
                'location' => $listing->location,
                'tags' => array_values(array_filter(array_map('trim', explode(',', $listing->tags)))),
                'isNew' => ! $listing->pivot->seen,
            ]);

        // No slogans for the demo account
        $slogan = null;
        if (! $user->is_demo) {
            $random = Slogan::inRandomOrder()->first();
            $slogan = $random
                ? ['html' => SafeHtml::clean($random->sloganPhrase), 'author' => $random->author]
                : ['html' => 'Find your albums', 'author' => 'TheBoise'];
        }

        return Inertia::render('Albums/Index', [
            'albums' => $albums,
            'slogan' => $slogan,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tag' => $filters['tag'] ?? '',
            ],
        ]);
    }

    // Show single listing (only for the people in the album)
    public function show(Listing $listing)
    {
        $user = auth()->user();

        $membership = $listing->listingUser()->where('user_id', $user->id)->first();
        if (is_null($membership)) {
            abort(403, 'Unauthorized Action');
        }

        if (! $membership->seen) {
            ListingUser::where('user_id', $user->id)
                ->where('listing_id', $listing->id)
                ->update(['seen' => true]);
        }

        $listing->load(['users', 'comments.user', 'comments.likes.user']);

        return Inertia::render('Albums/Show', [
            'album' => [
                'id' => $listing->id,
                'title' => $listing->title,
                'cover' => Media::url($listing->logo) ?? asset('images/no-image.png'),
                'dateFrom' => $listing->dateFrom,
                'dateTo' => $listing->dateTo,
                'location' => $listing->location,
                'placeName' => $listing->place_name,
                'latitude' => $listing->latitude ? (float) $listing->latitude : null,
                'longitude' => $listing->longitude ? (float) $listing->longitude : null,
                'tags' => $this->tags($listing->tags),
                'description' => SafeHtml::clean($listing->description),
                'albumLink' => $listing->albumLink,
                'videoLinks' => $this->videoLinks($listing->videoLinks),
                'people' => $listing->users->map(fn (User $person) => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'avatar' => Media::url($person->profilePicture),
                ])->values(),
            ],
            'comments' => $listing->comments->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'author' => $comment->user?->name ?? 'Deleted user',
                'avatar' => Media::url($comment->user?->profilePicture),
                'html' => SafeHtml::clean($comment->comment),
                'createdAt' => $comment->created_at?->toIso8601String(),
                'likes' => $comment->likeSummary($user),
            ])->values(),
            'can' => [
                'comment' => ! $user->is_demo,
                'manage' => $user->isAdmin == 1,
            ],
        ]);
    }

    // Show Create Form
    public function create()
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        return Inertia::render('Albums/Form', $this->formProps(null));
    }

    // Store Listing Data
    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        $formFields = $this->validateAlbum($request);
        $formFields['user_id'] = auth()->id();
        $formFields['logo'] = $request->hasFile('cover') ? AlbumCover::store($request->file('cover')) : null;

        $listing = Listing::create($formFields);
        $listing->users()->attach($request->people);

        // send email to the people in the album
        foreach (User::findMany($request->people) as $user) {
            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'albumTitle' => $listing->title,
                'albumLink' => 'https://theboise.it/listings/' . $listing->id,
                'albumLogo' => $listing->logo
            );
            Mail::send('/mails/new_album', $email_data, function ($message) use ($email_data) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject('New album ' . $email_data['albumTitle'] . ' added to theboise.it')
                    ->from('info@theboise.it', 'TheBoise');
            });
        }

        return redirect('/listings/' . $listing->id)->with('message', 'Album created successfully!');
    }

    // Show Edit Form
    public function edit(Listing $listing)
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        return Inertia::render('Albums/Form', $this->formProps($listing));
    }

    // Update Listing Data (admins, like the edit form)
    public function update(Request $request, Listing $listing)
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        $formFields = $this->validateAlbum($request);

        if ($request->hasFile('cover')) {
            if ($listing->logo) {
                Storage::disk('public')->delete($listing->logo);
            }
            $formFields['logo'] = AlbumCover::store($request->file('cover'));
        }

        $listing->users()->sync($request->people);
        $listing->update($formFields);

        return redirect('/listings/' . $listing->id)->with('message', 'Album updated successfully!');
    }

    // Delete Listing (admins)
    public function destroy(Listing $listing)
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        if ($listing->logo && Storage::disk('public')->exists($listing->logo)) {
            Storage::disk('public')->delete($listing->logo);
        }
        $listing->delete();

        return redirect('/')->with('message', 'Album deleted');
    }

    // Manage Listings
    public function manage()
    {
        $user = auth()->user();
        if ($user) {
            $listings = $user->listing()->get();
        }

        return view('listings.manage', ['listings' => $user ? $listings : []]);
    }

    // Store Comments Data (people in the album only). Comments are plain text with emojis:
    // stored as safe HTML so they display like the old formatted ones
    public function comments(Request $request)
    {
        $formFields = $request->validate([
            'listing_id' => 'required|integer',
            'comment' => 'required|string|max:5000'
        ]);

        $listing = Listing::findOrFail($formFields['listing_id']);
        $author = auth()->user();
        abort_unless($listing->users->contains('id', $author->id), 403, 'Unauthorized Action');

        $html = '<p>' . nl2br(e(trim($formFields['comment'])), false) . '</p>';

        Comment::create([
            'listing_id' => $listing->id,
            'user_id' => $author->id,
            'comment' => $html,
        ]);

        foreach ($listing->users as $user) {
            if ($author->id == $user['id']) {
                continue;
            }

            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'commentOfUser' => $author->name,
                'comment' => $html,
                'albumTitle' => $listing->title,
                'albumLink' => 'https://theboise.it/listings/' . $listing->id
            );
            Mail::send('/mails/new_comment', $email_data, function ($message) use ($email_data) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject('New comment added to album ' . $email_data['albumTitle'] . ' inside theboise.it')
                    ->from('info@theboise.it', 'TheBoise');
            });
        }

        return back()->with('message', 'Comment added!');
    }

    private function validateAlbum(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'placeName' => 'nullable|string|max:255',
            'albumLink' => 'required|url|max:255',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string|max:50',
            'description' => 'required|string',
            'videoLinks' => 'nullable|array',
            'videoLinks.*' => 'url|max:500',
            'people' => 'required|array|min:1',
            'people.*' => 'integer|exists:users,id',
            'cover' => 'nullable|image|max:20480',
        ]);

        $tags = collect($data['tags'])->map(fn ($tag) => trim(str_replace(',', ' ', $tag)))->filter();
        $videoLinks = collect($data['videoLinks'] ?? [])->filter()->values();
        if ($videoLinks->isNotEmpty() && ! $tags->contains('Youtube')) {
            $tags->push('Youtube');
        }

        $hasCoordinates = isset($data['latitude'], $data['longitude']);

        return [
            'title' => $data['title'],
            'dateFrom' => $data['dateFrom'],
            'dateTo' => $data['dateTo'],
            'location' => $data['location'],
            'latitude' => $hasCoordinates ? $data['latitude'] : null,
            'longitude' => $hasCoordinates ? $data['longitude'] : null,
            // Set (or confirmed) by the admin in the form
            'coordinates_source' => $hasCoordinates ? 'manual' : null,
            'place_name' => $hasCoordinates ? ($data['placeName'] ?? null) : null,
            'albumLink' => $data['albumLink'],
            'tags' => $tags->unique()->implode(', '),
            'description' => SafeHtml::clean($data['description']),
            'videoLinks' => $videoLinks->isNotEmpty() ? $videoLinks->implode(',') : null,
        ];
    }

    private function formProps(?Listing $listing): array
    {
        return [
            'album' => $listing ? [
                'id' => $listing->id,
                'title' => $listing->title,
                'dateFrom' => $listing->dateFrom,
                'dateTo' => $listing->dateTo,
                'location' => $listing->location,
                'latitude' => $listing->latitude ? (float) $listing->latitude : null,
                'longitude' => $listing->longitude ? (float) $listing->longitude : null,
                'placeName' => $listing->place_name,
                'coordinatesSuggested' => $listing->coordinates_source === 'suggested',
                'albumLink' => $listing->albumLink,
                'tags' => $this->tags($listing->tags),
                'description' => SafeHtml::clean($listing->description),
                'videoLinks' => $this->videoLinks($listing->videoLinks),
                'people' => $listing->users()->pluck('users.id'),
                'cover' => Media::url($listing->logo),
            ] : null,
            'users' => User::where('is_demo', false)->orderBy('name')->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture)]),
            // Every tag already used, most used first, to suggest them while typing
            'knownTags' => Listing::pluck('tags')
                ->flatMap(fn ($tags) => $this->tags($tags))
                ->reject(fn ($tag) => $tag === 'Youtube')
                ->countBy()->sortDesc()->keys()->values(),
        ];
    }

    private function tags(?string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
    }

    private function videoLinks(?string $links): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $links))));
    }
}
