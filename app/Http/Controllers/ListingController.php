<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Listing;
use App\Models\Slogan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Comment;
use App\Models\ListingUser;
use App\Models\AlbumEvent;
use App\Support\AlbumHistory;
use App\Support\Notify;
use App\Support\Locale;
use App\Support\AlbumCover;
use App\Support\Media;
use App\Support\SafeHtml;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

class ListingController extends Controller
{
    // Show all listings (the albums the user is in)
    public function index()
    {
        $user = auth()->user();
        $filters = request()->only(['tag', 'search']);

        $ownFilter = self::ownFilter($user, self::viewChoice());
        $albums = $user->listing()
            ->withPivot('seen')
            ->when($ownFilter['on'], fn ($query) => $query->where('listings.user_id', $user->id))
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
            'ownFilter' => $ownFilter,
            'wrappedBanner' => $this->wrappedBanner($user),
            'pinned' => $this->pinnedPost($user),
            // Falling snow, pumpkins, confetti...: today's, or the one an admin previews (?decoration=halloween)
            'decoration' => $user->isAdmin == 1 && request('decoration')
                ? \App\Support\Decorations::preview(request('decoration'), $user)
                : \App\Support\Decorations::today($user),
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

        // People in the album; admins can open any album to check it (read only, they're not added)
        $membership = $listing->listingUser()->where('user_id', $user->id)->first();
        abort_if(is_null($membership) && $user->isAdmin != 1, 403, 'Unauthorized Action');

        if ($membership && ! $membership->seen) {
            ListingUser::where('user_id', $user->id)
                ->where('listing_id', $listing->id)
                ->update(['seen' => true]);
        }

        $listing->load(['users', 'places', 'comments.user', 'comments.likes.user', 'user']);

        // Expense groups of this trip I'm in (the table doesn't exist until its migration runs)
        $expenseGroups = Schema::hasTable('expense_groups') && ! auth()->user()->is_demo
            ? auth()->user()->expenseGroups()->where('listing_id', $listing->id)->get(['expense_groups.id', 'name'])
                ->map(fn ($group) => ['id' => $group->id, 'name' => $group->name])
            : [];

        $trip = Schema::hasTable('trips') && ! $user->is_demo
            ? $user->trips()->where('listing_id', $listing->id)->first(['trips.id', 'title'])
            : null;

        return Inertia::render('Albums/Show', [
            'expenseGroups' => $expenseGroups,
            'trip' => $trip ? ['id' => $trip->id, 'title' => $trip->title] : null,
            'album' => [
                'id' => $listing->id,
                'title' => $listing->title,
                'cover' => Media::url($listing->logo) ?? asset('images/no-image.png'),
                'dateFrom' => $listing->dateFrom,
                'dateTo' => $listing->dateTo,
                'location' => $listing->location,
                'places' => $this->places($listing),
                'tags' => $this->tags($listing->tags),
                // The viewer's own link/description when the admin set one, otherwise the general ones.
                // Nothing tells them it's personal: everyone must think they see what everyone else sees
                'description' => SafeHtml::clean($membership?->custom_description ?: $listing->description),
                'albumLink' => $membership?->custom_album_link ?: $listing->albumLink,
                // Only for admins: how many people see a different version
                'personalizedCount' => $user->isAdmin == 1 && ListingUser::hasPersonalVersions()
                    ? $listing->listingUser()->where(fn ($query) => $query->whereNotNull('custom_album_link')->orWhereNotNull('custom_description'))->count()
                    : 0,
                'videoLinks' => $this->videoLinks($listing->videoLinks),
                'postedBy' => $listing->user?->name,
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
                'comment' => ! $user->is_demo && $membership,
                'adminView' => is_null($membership),
                'edit' => $user->canEditAlbum($listing),
                'delete' => $user->isAdmin == 1,
            ],
            // Admins: what happened to this album
            'history' => $user->isAdmin == 1 && Schema::hasTable('album_events')
                ? AlbumEvent::with('user')->where('listing_id', $listing->id)->latest('id')->limit(30)->get()->map(fn ($event) => AlbumHistory::describe($event))
                : [],
        ]);
    }

    // Show Create Form
    public function create()
    {
        abort_unless(auth()->user()->canPostAlbums(), 403, 'Unauthorized Action');

        return Inertia::render('Albums/Form', $this->formProps(null) + ['fromTrip' => $this->tripToAlbum(request('trip'))]);
    }

    // Store Listing Data
    public function store(Request $request)
    {
        $poster = auth()->user();
        abort_unless($poster->canPostAlbums(), 403, 'Unauthorized Action');

        $formFields = $this->validateAlbum($request);
        $people = $this->people($request, $poster);
        $formFields['user_id'] = $poster->id;
        $formFields['logo'] = $request->hasFile('cover') ? AlbumCover::store($request->file('cover')) : null;

        $listing = Listing::create($formFields);
        $listing->users()->attach($people);
        $this->savePlaces($listing, $request->input('places', []));
        if ($poster->isAdmin == 1) {
            $this->savePersonalVersions($listing, $request->input('personal', []));
        }
        AlbumEvent::record('created', $listing, ['people' => User::findMany($people)->pluck('name')->sort()->values()->all()]);

        // Made from a planned trip: they stay linked, and the trip's expenses point to the album
        if ($trip = $this->tripForAlbum($request->input('trip_id'))) {
            $trip->update(['listing_id' => $listing->id]);
            if ($trip->expenseGroup && ! $trip->expenseGroup->listing_id) {
                $trip->expenseGroup->update(['listing_id' => $listing->id]);
            }
        }

        // Tell the people in the album (not who posted it): email and/or phone, as each prefers
        $recipients = User::findMany($people)->where('id', '!=', $poster->id);
        Notify::push($recipients, 'album', fn () => [
            'title' => __('New album: :title', ['title' => $listing->title]),
            'body' => __(':name posted it. Have a look!', ['name' => $poster->name]),
            'url' => '/listings/' . $listing->id,
            'tag' => 'album-' . $listing->id,
            'image' => Media::url($listing->logo),
        ]);
        foreach ($recipients as $user) {
            if (! Notify::wants($user, 'album', 'email')) {
                continue;
            }
            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'albumTitle' => $listing->title,
                'listing' => $listing,
                'postedBy' => $poster->name,
            );
            Locale::for($user, fn () => Mail::send('/mails/new_album', $email_data, function ($message) use ($email_data) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject(__('New album :title added to theboise.it', ['title' => $email_data['albumTitle']]))
                    ->from('info@theboise.it', 'TheBoise');
            }));
        }

        // Someone who isn't an admin posted it: the admins not in the album hear about it too
        if ($poster->isAdmin != 1) {
            $admins = User::where('isAdmin', 1)->where('is_demo', false)->whereNotIn('id', $people)->get();
            foreach ($admins as $admin) {
                Locale::for($admin, fn () => Mail::send('/mails/album_posted', ['name' => $admin->name, 'postedBy' => $poster->name, 'listing' => $listing, 'peopleCount' => count($people)], function ($message) use ($admin, $listing, $poster) {
                    $message->to($admin->email, $admin->name)
                        ->subject(__(':name posted the album :title', ['name' => $poster->name, 'title' => $listing->title]) . ' - theboise.it')
                        ->from('info@theboise.it', 'TheBoise');
                }));
            }
        }

        return redirect('/listings/' . $listing->id)->with('message', 'Album created successfully!');
    }

    // Show Edit Form
    public function edit(Listing $listing)
    {
        abort_unless(auth()->user()->canEditAlbum($listing), 403, 'Unauthorized Action');

        return Inertia::render('Albums/Form', $this->formProps($listing));
    }

    // Update Listing Data (admins, and who posted it while they can still post)
    public function update(Request $request, Listing $listing)
    {
        $editor = auth()->user();
        abort_unless($editor->canEditAlbum($listing), 403, 'Unauthorized Action');

        $formFields = $this->validateAlbum($request, $listing);
        $people = $this->people($request, $editor, $listing);
        $before = AlbumHistory::snapshot($listing);
        $peopleBefore = $listing->users()->pluck('users.id');

        if ($request->hasFile('cover')) {
            if ($listing->logo) {
                Storage::disk('public')->delete($listing->logo);
            }
            $formFields['logo'] = AlbumCover::store($request->file('cover'));
        }

        $listing->users()->sync($people);
        $listing->update($formFields);
        $this->savePlaces($listing, $request->input('places', []));
        // Only admins set the personal versions: an edit by the poster keeps them as they are
        if ($editor->isAdmin == 1) {
            $this->savePersonalVersions($listing, $request->input('personal', []));
        }

        $changes = AlbumHistory::changes($before, AlbumHistory::snapshot($listing->fresh()));
        $added = User::findMany(collect($people)->diff($peopleBefore))->pluck('name')->sort()->values()->all();
        $removed = User::findMany($peopleBefore->diff($people))->pluck('name')->sort()->values()->all();
        if ($changes || $added || $removed) {
            AlbumEvent::record('updated', $listing, array_filter(['changes' => $changes, 'added' => $added, 'removed' => $removed]));
        }

        return redirect('/listings/' . $listing->id)->with('message', 'Album updated successfully!');
    }

    // Delete Listing (admins)
    public function destroy(Listing $listing)
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        if ($listing->logo && Storage::disk('public')->exists($listing->logo)) {
            Storage::disk('public')->delete($listing->logo);
        }
        AlbumEvent::record('deleted', $listing);
        $listing->delete();

        return redirect('/')->with('message', 'Album deleted');
    }

    // The old "manage" page listed the same albums as the home page
    public function manage()
    {
        return redirect('/');
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

        $comment = Comment::create([
            'listing_id' => $listing->id,
            'user_id' => $author->id,
            'comment' => $html,
        ]);

        // Short on purpose: it can show on a locked screen
        Notify::push($listing->users->where('id', '!=', $author->id), 'comment', fn () => [
            'title' => __(':name commented on :title', ['name' => $author->name, 'title' => $listing->title]),
            'body' => __('Tap to read it and answer.'),
            'url' => "/listings/{$listing->id}#comment-{$comment->id}",
            'tag' => 'album-comments-' . $listing->id,
        ]);

        foreach ($listing->users as $user) {
            if ($author->id == $user['id'] || ! Notify::wants($user, 'comment', 'email')) {
                continue;
            }

            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'commentOfUser' => $author->name,
                'comment' => $html,
                'albumTitle' => $listing->title,
                'albumLink' => url('/listings/' . $listing->id),
            );
            Locale::for($user, fn () => Mail::send('/mails/new_comment', $email_data, function ($message) use ($email_data) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject(__('New comment on the album :title - theboise.it', ['title' => $email_data['albumTitle']]))
                    ->from('info@theboise.it', 'TheBoise');
            }));
        }

        return back()->with('message', 'Comment added!');
    }

    // A trip of mine without an album yet (null otherwise, also before the trips migration)
    private function tripForAlbum($id): ?\App\Models\Trip
    {
        if (! $id || ! Schema::hasTable('trips')) {
            return null;
        }
        $trip = \App\Models\Trip::with('members')->find($id);

        return $trip && ! $trip->listing_id && $trip->hasMember(auth()->user()) ? $trip : null;
    }

    // What the album form starts with when made from a trip
    private function tripToAlbum($id): ?array
    {
        $trip = $this->tripForAlbum($id);

        return $trip ? [
            'id' => $trip->id,
            'title' => $trip->title,
            'dateFrom' => $trip->date_from?->toDateString(),
            'dateTo' => $trip->date_to?->toDateString(),
            'location' => $trip->destination,
            'people' => $trip->members->where('is_demo', false)->pluck('id')->values(),
        ] : null;
    }

    // The post the admins pinned at the top, if this person can see it (never the demo account: no forum)
    private function pinnedPost(User $user): ?array
    {
        $id = \App\Support\Settings::get(\App\Support\Settings::PINNED_POST);
        $post = $id && ! $user->is_demo ? \App\Models\Post::with('category')->find($id) : null;

        return $post && $post->isVisibleTo($user)
            ? ['id' => $post->id, 'title' => $post->title, 'url' => "/forum/{$post->category_id}/{$post->id}"]
            : null;
    }

    // December: this year's Wrapped; January: last year's (if there are albums)
    private function wrappedBanner(User $user): ?int
    {
        $year = match (now()->month) {
            \App\Support\Wrapped::OPENS_MONTH => now()->year,
            1 => now()->year - 1,
            default => null,
        };

        return $year && $user->listing()->whereYear('dateFrom', $year)->exists() ? $year : null;
    }

    // "All albums | My albums": offered to who posted at least one album. The default is saved on the
    // account (Preferences); the switch on the album list and the map only changes the current view
    // (?mine=1/0, not in the address bar: a reload goes back to the default)
    public static function ownFilter(User $user, ?bool $view = null): array
    {
        $available = ! $user->is_demo && Listing::where('user_id', $user->id)->exists();
        $default = (bool) $user->only_own_albums;

        return ['available' => $available, 'default' => $available && $default, 'on' => $available && ($view ?? $default)];
    }

    // The view asked by the switch, if any
    public static function viewChoice(): ?bool
    {
        return request()->has('mine') ? request()->boolean('mine') : null;
    }

    // Who's in the album: the poster always is (unless an admin), the demo account only by an admin
    // (when the poster edits, the demo account stays if an admin had added it)
    private function people(Request $request, User $editor, ?Listing $listing = null): array
    {
        $people = collect($request->input('people', []))->map(fn ($id) => (int) $id);
        if ($editor->isAdmin == 1) {
            return $people->unique()->values()->all();
        }

        $demo = User::where('is_demo', true)->pluck('id');
        $people = $people->diff($demo);
        if ($listing) {
            $people = $people->merge($listing->users()->whereIn('users.id', $demo)->pluck('users.id'));
        }
        $owner = $listing ? $listing->user_id : $editor->id;

        return $people->push($owner)->unique()->values()->all();
    }

    private function validateAlbum(Request $request, ?Listing $listing = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
            'location' => 'required|string|max:255',
            'places' => 'nullable|array|max:20',
            'places.*.latitude' => 'required|numeric|between:-90,90',
            'places.*.longitude' => 'required|numeric|between:-180,180',
            'places.*.placeName' => 'nullable|string|max:255',
            'albumLink' => 'required|url|max:255',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string|max:50',
            'description' => 'required|string',
            'videoLinks' => 'nullable|array',
            'videoLinks.*' => 'url|max:500',
            'people' => 'required|array|min:1',
            'people.*' => 'integer|exists:users,id',
            'personal' => 'nullable|array',
            'personal.*.userId' => ['required', 'integer', 'distinct', Rule::in($request->input('people', []))],
            'personal.*.albumLink' => 'nullable|url|max:255',
            'personal.*.description' => 'nullable|string',
            // Every album has a preview: required when creating, and when editing one that has none
            'cover' => [$listing?->logo ? 'nullable' : 'required', 'image', 'max:20480'],
        ]);

        $tags = collect($data['tags'])->map(fn ($tag) => trim(str_replace(',', ' ', $tag)))->filter();
        $videoLinks = collect($data['videoLinks'] ?? [])->filter()->values();
        if ($videoLinks->isNotEmpty() && ! $tags->contains('Youtube')) {
            $tags->push('Youtube');
        }

        return [
            'title' => $data['title'],
            'dateFrom' => $data['dateFrom'],
            'dateTo' => $data['dateTo'],
            'location' => $data['location'],
            'albumLink' => $data['albumLink'],
            'tags' => $tags->unique()->implode(', '),
            'description' => SafeHtml::clean($data['description']),
            'videoLinks' => $videoLinks->isNotEmpty() ? $videoLinks->implode(',') : null,
        ];
    }

    private function formProps(?Listing $listing): array
    {
        $isAdmin = auth()->user()->isAdmin == 1;

        return [
            'canPersonalize' => $isAdmin,
            'alwaysIn' => $isAdmin ? null : ($listing?->user_id ?? auth()->id()),
            'album' => $listing ? [
                'id' => $listing->id,
                'title' => $listing->title,
                'dateFrom' => $listing->dateFrom,
                'dateTo' => $listing->dateTo,
                'location' => $listing->location,
                'places' => $this->places($listing),
                'albumLink' => $listing->albumLink,
                'tags' => $this->tags($listing->tags),
                'description' => SafeHtml::clean($listing->description),
                'videoLinks' => $this->videoLinks($listing->videoLinks),
                'people' => $listing->users()->pluck('users.id'),
                // Admins only: anything given to the page reaches the browser, even if not shown
                'personal' => ! $isAdmin || ! ListingUser::hasPersonalVersions() ? [] : $listing->listingUser()
                    ->where(fn ($query) => $query->whereNotNull('custom_album_link')->orWhereNotNull('custom_description'))
                    ->get()
                    ->map(fn ($row) => [
                        'userId' => $row->user_id,
                        'albumLink' => $row->custom_album_link ?? '',
                        'description' => $row->custom_description ?? '',
                    ]),
                'cover' => Media::url($listing->logo),
            ] : null,
            // Everyone, the demo account too (for admins: adding it to an album is how recruiters get to see it)
            'users' => User::orderBy('name')->when(! $isAdmin, fn ($query) => $query->where('is_demo', false))->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture), 'isDemo' => (bool) $user->is_demo]),
            // Every tag already used, most used first, to suggest them while typing
            'knownTags' => Listing::pluck('tags')
                ->flatMap(fn ($tags) => $this->tags($tags))
                ->reject(fn ($tag) => $tag === 'Youtube')
                ->countBy()->sortDesc()->keys()->values(),
        ];
    }

    private function places(Listing $listing): array
    {
        return $listing->places->map(fn ($place) => [
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'placeName' => $place->place_name,
            'suggested' => $place->source === 'suggested',
        ])->all();
    }

    // The places in the form replace the album's places; saving the form confirms them
    private function savePlaces(Listing $listing, array $places): void
    {
        $listing->places()->delete();
        foreach (array_values($places) as $position => $place) {
            $listing->places()->create([
                'latitude' => round((float) $place['latitude'], 7),
                'longitude' => round((float) $place['longitude'], 7),
                'place_name' => isset($place['placeName']) ? mb_substr($place['placeName'], 0, 255) : null,
                'source' => 'manual',
                'position' => $position,
            ]);
        }
    }

    // Each person's own link/description (the people not in the list get the general ones again)
    private function savePersonalVersions(Listing $listing, array $versions): void
    {
        ListingUser::where('listing_id', $listing->id)->update(['custom_album_link' => null, 'custom_description' => null]);

        foreach ($versions as $version) {
            $description = SafeHtml::clean($version['description'] ?? '');
            ListingUser::where('listing_id', $listing->id)->where('user_id', $version['userId'])->update([
                'custom_album_link' => ($version['albumLink'] ?? '') ?: null,
                'custom_description' => trim(strip_tags($description)) !== '' ? $description : null,
            ]);
        }
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
