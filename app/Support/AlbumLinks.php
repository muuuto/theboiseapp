<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

// Albums of the same trip, posted by different people ("Sicilia" by Anna and "Sicilia" by Bob):
// they share a group number (listings.trip_group), and each album page shows the others.
// Linked from the album form: "Same trip" picks another album, "Unlink" leaves the group
class AlbumLinks
{
    public static function ready(): bool
    {
        return Schema::hasColumn('listings', 'trip_group');
    }

    // The album form saved: leave the group and/or join another album's
    public static function save(Request $request, Listing $listing, User $user): void
    {
        if (! self::ready()) {
            return;
        }
        $data = $request->validate([
            'sameTripAs' => 'nullable|integer|exists:listings,id',
            'unlinkTrip' => 'nullable|boolean',
        ]);
        if (! empty($data['unlinkTrip'])) {
            self::unlink($listing);
        }
        $other = ! empty($data['sameTripAs']) ? Listing::find($data['sameTripAs']) : null;
        // Only an album they can see (admins: any)
        if ($other && $other->id !== $listing->id && self::visible(Listing::whereKey($other->id), $user)->exists()) {
            self::link($listing->fresh(), $other);
        }
    }

    // Together; linking to an album already in a group joins it (two groups become one)
    public static function link(Listing $listing, Listing $other): void
    {
        $group = $other->trip_group ?? $listing->trip_group ?? min($listing->id, $other->id);
        $old = array_filter([$listing->trip_group, $other->trip_group], fn ($id) => $id && $id !== $group);
        if ($old) {
            Listing::whereIn('trip_group', $old)->update(['trip_group' => $group]);
        }
        Listing::whereKey([$listing->id, $other->id])->update(['trip_group' => $group]);
    }

    // Out of its group (a group of one isn't a group)
    public static function unlink(Listing $listing): void
    {
        $group = $listing->trip_group;
        if (! $group) {
            return;
        }
        $listing->update(['trip_group' => null]);
        if (Listing::where('trip_group', $group)->count() < 2) {
            Listing::where('trip_group', $group)->update(['trip_group' => null]);
        }
    }

    // The other albums of its group that $user is in (admins: all)
    public static function linked(?Listing $listing, User $user): Collection
    {
        if (! $listing?->trip_group || ! self::ready()) {
            return collect();
        }

        return self::visible(Listing::with('user')->where('trip_group', $listing->trip_group)->where('id', '!=', $listing->id), $user)
            ->orderBy('dateFrom')->get()->map(fn ($album) => self::card($album))->values();
    }

    // For the form's "Same trip": the albums $user is in, not already in this album's group
    // (the form puts the ones with dates near the album's first)
    public static function linkable(?Listing $listing, User $user): ?Collection
    {
        if (! self::ready()) {
            return null;
        }

        return self::visible(Listing::with('user')
            ->when($listing, fn ($query) => $query->where('id', '!=', $listing->id)
                ->where(fn ($query) => $query->whereNull('trip_group')->orWhere('trip_group', '!=', $listing->trip_group ?? 0))), $user)
            ->get(['id', 'title', 'logo', 'dateFrom', 'dateTo', 'user_id'])
            ->map(fn ($album) => self::card($album))->values();
    }

    private static function visible($query, User $user)
    {
        return $user->isAdmin == 1 ? $query : $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id));
    }

    private static function card(Listing $album): array
    {
        return [
            'id' => $album->id,
            'title' => $album->title,
            'cover' => Media::url($album->logo) ?? asset('images/no-image.png'),
            'dateFrom' => $album->dateFrom,
            'dateTo' => $album->dateTo,
            'postedBy' => $album->user?->name,
        ];
    }
}
