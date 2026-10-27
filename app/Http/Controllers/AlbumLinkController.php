<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// Albums of the same trip, posted by different people ("Sicilia" by Anna and "Sicilia" by Bob):
// linked together, each album page shows the others. Who can edit an album links it to another
// album they're in; linking to an album already in a group joins that group
class AlbumLinkController extends Controller
{
    public function store(Request $request, Listing $listing)
    {
        $user = $request->user();
        abort_unless(Schema::hasColumn('listings', 'trip_group'), 409, 'Run the pending migrations first');
        abort_unless($user->canEditAlbum($listing), 403, 'Unauthorized Action');
        $data = $request->validate(['with' => 'required|integer|exists:listings,id']);
        $other = Listing::findOrFail($data['with']);
        abort_if($other->id === $listing->id, 422);
        // Only albums they can see (admins: any)
        abort_unless($user->isAdmin == 1 || $other->users()->where('users.id', $user->id)->exists(), 403, 'Unauthorized Action');

        $group = $other->trip_group ?? $listing->trip_group ?? min($listing->id, $other->id);
        // Two groups become one
        $old = array_filter([$listing->trip_group, $other->trip_group], fn ($id) => $id && $id !== $group);
        if ($old) {
            Listing::whereIn('trip_group', $old)->update(['trip_group' => $group]);
        }
        Listing::whereKey([$listing->id, $other->id])->update(['trip_group' => $group]);

        return back()->with('message', __('Linked to ":title"', ['title' => $other->title]));
    }

    // Out of its group (a group of one isn't a group)
    public function destroy(Request $request, Listing $listing)
    {
        abort_unless($request->user()->canEditAlbum($listing), 403, 'Unauthorized Action');
        $group = $listing->trip_group;
        if ($group) {
            $listing->update(['trip_group' => null]);
            if (Listing::where('trip_group', $group)->count() < 2) {
                Listing::where('trip_group', $group)->update(['trip_group' => null]);
            }
        }

        return back()->with('message', 'Album unlinked');
    }
}
