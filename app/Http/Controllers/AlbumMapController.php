<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\Media;
use Inertia\Inertia;

class AlbumMapController extends Controller
{
    // The user's albums that have coordinates, on one map
    public function index()
    {
        $albums = auth()->user()->listing()
            ->whereNotNull('latitude')
            ->orderBy('dateFrom', 'desc')
            ->get()
            ->map(fn (Listing $listing) => $this->marker($listing));

        return Inertia::render('Albums/Map', [
            'albums' => $albums,
            'withoutCoordinates' => auth()->user()->listing()->whereNull('latitude')->count(),
        ]);
    }

    // Admin: albums whose coordinates were only guessed from the location text, or are missing
    public function review()
    {
        $albums = Listing::where(fn ($query) => $query->where('coordinates_source', 'suggested')->orWhereNull('latitude'))
            ->orderBy('dateFrom', 'desc')
            ->get()
            ->map(fn (Listing $listing) => $this->marker($listing) + ['missing' => is_null($listing->latitude)]);

        return Inertia::render('Admin/Locations', ['albums' => $albums]);
    }

    public function confirm(Listing $listing)
    {
        abort_if(is_null($listing->latitude), 422, 'This album has no coordinates to confirm');

        $listing->update(['coordinates_source' => 'manual']);

        return back()->with('message', 'Location confirmed');
    }

    // After removing or fixing the wrong ones: accept every remaining guess at once
    public function confirmAll()
    {
        $count = Listing::where('coordinates_source', 'suggested')->update(['coordinates_source' => 'manual']);

        return back()->with('message', "{$count} locations confirmed");
    }

    // Wrong guess: remove it (the album just won't be on the map until set in the album form)
    public function clear(Listing $listing)
    {
        $listing->update(['latitude' => null, 'longitude' => null, 'coordinates_source' => null, 'place_name' => null]);

        return back()->with('message', 'Location removed');
    }

    private function marker(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'cover' => Media::url($listing->logo) ?? asset('images/no-image.png'),
            'dateFrom' => $listing->dateFrom,
            'location' => $listing->location,
            'placeName' => $listing->place_name,
            'latitude' => $listing->latitude ? (float) $listing->latitude : null,
            'longitude' => $listing->longitude ? (float) $listing->longitude : null,
            'suggested' => $listing->coordinates_source === 'suggested',
        ];
    }
}
