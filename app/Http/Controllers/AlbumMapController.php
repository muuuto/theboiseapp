<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\ListingPlace;
use App\Support\Media;
use Inertia\Inertia;

class AlbumMapController extends Controller
{
    // The places of the user's albums on one map (an album in two places has two pins)
    public function index()
    {
        $user = auth()->user();
        $ownFilter = ListingController::ownFilter($user);
        $albums = $user->listing()->with('places')
            ->when($ownFilter['on'], fn ($query) => $query->where('listings.user_id', $user->id))
            ->get();

        return Inertia::render('Albums/Map', [
            'ownFilter' => $ownFilter,
            'places' => $albums->flatMap(fn (Listing $listing) => $listing->places->map(fn ($place) => $this->marker($listing, $place)))->values(),
            'withoutPlaces' => $albums->filter(fn (Listing $listing) => $listing->places->isEmpty())->count(),
        ]);
    }

    // Admin: the places only guessed from the location text, and the albums without any place
    public function review()
    {
        $suggested = ListingPlace::with('listing')->where('source', 'suggested')->get()
            ->map(fn ($place) => $this->marker($place->listing, $place) + ['location' => $place->listing->location]);

        $missing = Listing::doesntHave('places')->orderBy('dateFrom', 'desc')->get()
            ->map(fn (Listing $listing) => ['id' => $listing->id, 'title' => $listing->title, 'location' => $listing->location]);

        return Inertia::render('Admin/Locations', ['places' => $suggested->values(), 'missing' => $missing]);
    }

    public function confirm(ListingPlace $place)
    {
        $place->update(['source' => 'manual']);

        return back()->with('message', 'Location confirmed');
    }

    // After removing or fixing the wrong ones: accept every remaining guess at once
    public function confirmAll()
    {
        $count = ListingPlace::where('source', 'suggested')->update(['source' => 'manual']);

        return back()->with('message', "{$count} locations confirmed");
    }

    // Wrong guess: remove that pin (the album keeps its other places, if any)
    public function clear(ListingPlace $place)
    {
        $place->delete();

        return back()->with('message', 'Location removed');
    }

    private function marker(Listing $listing, ListingPlace $place): array
    {
        return [
            // Unique per pin; albumId is where the pin links to
            'id' => $place->id,
            'albumId' => $listing->id,
            'title' => $listing->title,
            'cover' => Media::url($listing->logo) ?? asset('images/no-image.png'),
            'dateFrom' => $listing->dateFrom,
            'placeName' => $place->place_name,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'suggested' => $place->source === 'suggested',
        ];
    }
}
