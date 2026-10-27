<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\GymWorkout;
use App\Models\Like;
use App\Models\PostComment;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Badges: earned from what someone did on the site, worked out when the page asks (nothing stored).
// Each one: [key, value so far, target] (the pages have the names and emojis, in both languages).
// $until: as things were on that day (Wrapped: the badges earned during a year); null = now
class Badges
{
    public static function for(User $user, bool $showGym, ?CarbonInterface $until = null): array
    {
        $by = $until?->copy()->endOfDay();
        $upTo = fn ($query, string $column) => $by ? $query->where($column, '<=', $by) : $query;

        $albums = $upTo($user->listing()->with('places'), 'dateFrom')->get();
        $places = $albums->flatMap->places;
        $home = $user->home()['point'];
        $first = $albums->min('dateFrom');

        $badges = [
            ['explorer', $places->map(fn ($place) => Wrapped::country($place->place_name)['name'] ?? null)->filter()->unique()->count(), 10],
            ['globetrotter', (int) round($places->map(fn ($place) => Wrapped::distance($home, [(float) $place->latitude, (float) $place->longitude]))->max() ?? 0), 2000],
            ['mountaineer', (int) ($places->max('elevation') ?? 0), 2000],
            ['veteran', $albums->count(), 100],
            ['pioneer', $first ? (int) floor(($by ?? now())->diffInYears($first, true)) : 0, 10],
            ['photographer', $upTo(\App\Models\Listing::where('user_id', $user->id), 'created_at')->count(), 10],
            ['social', DB::table('listing_user')->whereIn('listing_id', $albums->pluck('id'))->where('user_id', '!=', $user->id)->distinct()->count('user_id'), 20],
            ['chatterbox', $upTo(Comment::where('user_id', $user->id), 'created_at')->count() + $upTo(PostComment::where('user_id', $user->id), 'created_at')->count(), 100],
            ['beloved', self::likesReceived($user, $by), 50],
        ];
        if (Schema::hasTable('trips')) {
            $badges[] = ['organiser', $upTo(Trip::where('created_by', $user->id), 'created_at')->count(), 5];
        }
        if (Trip::hasEvents()) {
            $badges[] = ['party', $upTo(DB::table('trip_user')->join('trips', 'trips.id', '=', 'trip_user.trip_id')
                ->where('trip_user.user_id', $user->id)->where('trips.kind', 'event')->where('trip_user.rsvp', 'yes'), 'trips.date_from')->count(), 10];
        }
        // The gym is private: only on their own page, or if they share their records
        if ($showGym && $user->canUseGym() && Schema::hasTable('gym_workouts')) {
            $badges[] = ['legday', $upTo(GymWorkout::where('user_id', $user->id), 'date')->count(), 30];
        }

        return collect($badges)->map(fn ($badge) => [
            'key' => $badge[0],
            'value' => $badge[1],
            'target' => $badge[2],
            'earned' => $badge[1] >= $badge[2],
        ])->sortByDesc('earned')->values()->all();
    }

    // The badges earned during a year: had by its end, not by the end of the year before
    public static function earnedIn(User $user, int $year): array
    {
        $had = collect(self::for($user, true, now()->setDate($year - 1, 12, 31)))->where('earned', true)->pluck('key');

        return collect(self::for($user, true, now()->setDate($year, 12, 31)->min(now())))
            ->where('earned', true)->reject(fn ($badge) => $had->contains($badge['key']))->values()->all();
    }

    private static function likesReceived(User $user, ?CarbonInterface $by): int
    {
        return Like::where(fn ($query) => $query
            ->where(fn ($query) => $query->where('likeable_type', 'album_comment')->whereIn('likeable_id', Comment::where('user_id', $user->id)->select('id')))
            ->orWhere(fn ($query) => $query->where('likeable_type', 'post_comment')->whereIn('likeable_id', PostComment::where('user_id', $user->id)->select('id'))))
            ->when($by, fn ($query) => $query->where('created_at', '<=', $by))
            ->count();
    }
}
