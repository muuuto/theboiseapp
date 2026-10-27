<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\User;
use App\Support\Badges;
use App\Support\Media;
use App\Support\Wrapped;
use Illuminate\Http\Request;
use Inertia\Inertia;

// People: everyone in the circle, and each person's page: their numbers, their badges, and what
// you share ("You and Anna: 14 albums together since 2016"). Only counts and the albums you're
// both in: never the titles of albums you're not in
class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $myAlbums = $me->listing()->pluck('listings.id');
        $people = User::where('is_demo', false)->withCount('listing')->orderBy('name')->get();
        $together = \Illuminate\Support\Facades\DB::table('listing_user')->whereIn('listing_id', $myAlbums)
            ->selectRaw('user_id, count(*) as albums')->groupBy('user_id')->pluck('albums', 'user_id');

        return Inertia::render('People/Index', [
            'people' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'avatar' => Media::url($person->profilePicture),
                'albums' => $person->listing_count,
                'together' => $person->id === $me->id ? null : (int) ($together[$person->id] ?? 0),
                'me' => $person->id === $me->id,
            ])->sortByDesc(fn ($person) => [$person['me'] ? 1 : 0, $person['together'] ?? 0])->values(),
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_if($user->is_demo, 404);
        $me = $request->user();
        $isMe = $user->id === $me->id;
        $albums = $user->listing()->with('places')->orderBy('dateFrom')->get();
        $countries = fn ($albums) => $albums->flatMap->places->map(fn ($place) => Wrapped::country($place->place_name))->filter()->unique('name')->values();

        $shared = $isMe ? null : $me->listing()->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->with(['places', 'user'])->orderByDesc('dateFrom')->get();

        return Inertia::render('People/Show', [
            'person' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => Media::url($user->profilePicture),
                'isMe' => $isMe,
            ],
            'stats' => [
                'albums' => $albums->count(),
                'since' => $albums->first() ? (int) substr($albums->first()->dateFrom, 0, 4) : null,
                'countries' => $countries($albums),
                'places' => $albums->flatMap->places->count(),
            ],
            // You and them: only the albums you're both in
            'together' => $shared ? [
                'albums' => $shared->count(),
                'since' => $shared->last() ? (int) substr($shared->last()->dateFrom, 0, 4) : null,
                'first' => $shared->last() ? ['id' => $shared->last()->id, 'title' => $shared->last()->title] : null,
                'countries' => $countries($shared),
                'list' => $shared->take(24)->map(fn (Listing $album) => [
                    'id' => $album->id,
                    'title' => $album->title,
                    'cover' => Media::url($album->logo) ?? asset('images/no-image.png'),
                    'dateFrom' => $album->dateFrom,
                ])->values(),
                'pins' => $shared->flatMap(fn (Listing $album) => $album->places->map(fn ($place) => [
                    'id' => $place->id,
                    'albumId' => $album->id,
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                    'title' => $album->title,
                    'cover' => Media::url($album->logo) ?? asset('images/no-image.png'),
                    'dateFrom' => $album->dateFrom,
                ]))->values(),
            ] : null,
            'badges' => Badges::for($user, $isMe || (bool) $user->gym_share_records),
        ]);
    }
}
