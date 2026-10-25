<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\PostComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// TheBoise Wrapped: someone's year in albums, places, friends and comments (like Spotify Wrapped).
// Only from what's already on the site, and only their own albums and comments
class Wrapped
{
    // Distances are counted from home and back, as the crow flies: each person's hometown
    // (Account → Preferences), Bolzano for who didn't set one
    public const HOME = [46.4983, 11.3548]; // Bolzano

    // The current year opens on this day; past years are always there
    public const OPENS_MONTH = 12;

    // Place names come from OpenStreetMap in Italian (sometimes English)
    private const COUNTRIES = [
        'Italia' => 'IT', 'Italy' => 'IT', 'Albania' => 'AL', 'Austria' => 'AT', 'Österreich' => 'AT', 'Francia' => 'FR', 'France' => 'FR',
        'Germania' => 'DE', 'Germany' => 'DE', 'Deutschland' => 'DE', 'Svizzera' => 'CH', 'Switzerland' => 'CH', 'Schweiz' => 'CH',
        'Spagna' => 'ES', 'Spain' => 'ES', 'España' => 'ES', 'Portogallo' => 'PT', 'Portugal' => 'PT', 'Regno Unito' => 'GB', 'United Kingdom' => 'GB',
        'Irlanda' => 'IE', 'Ireland' => 'IE', 'Croazia' => 'HR', 'Croatia' => 'HR', 'Hrvatska' => 'HR', 'Slovenia' => 'SI', 'Slovenija' => 'SI',
        'Repubblica Ceca' => 'CZ', 'Czechia' => 'CZ', 'Česko' => 'CZ', 'Slovacchia' => 'SK', 'Ungheria' => 'HU', 'Hungary' => 'HU', 'Polonia' => 'PL', 'Poland' => 'PL',
        'Paesi Bassi' => 'NL', 'Netherlands' => 'NL', 'Belgio' => 'BE', 'Belgium' => 'BE', 'Lussemburgo' => 'LU', 'Danimarca' => 'DK', 'Denmark' => 'DK',
        'Svezia' => 'SE', 'Sweden' => 'SE', 'Norvegia' => 'NO', 'Norway' => 'NO', 'Finlandia' => 'FI', 'Finland' => 'FI', 'Islanda' => 'IS', 'Iceland' => 'IS',
        'Grecia' => 'GR', 'Greece' => 'GR', 'Ελλάς' => 'GR', 'Malta' => 'MT', 'Cipro' => 'CY', 'Turchia' => 'TR', 'Türkiye' => 'TR', 'Turkey' => 'TR',
        'Montenegro' => 'ME', 'Bosnia ed Erzegovina' => 'BA', 'Serbia' => 'RS', 'Macedonia del Nord' => 'MK', 'Bulgaria' => 'BG', 'Romania' => 'RO',
        'Estonia' => 'EE', 'Lettonia' => 'LV', 'Lituania' => 'LT', 'Ucraina' => 'UA', 'Georgia' => 'GE', 'Armenia' => 'AM',
        'San Marino' => 'SM', 'Città del Vaticano' => 'VA', 'Monaco' => 'MC', 'Liechtenstein' => 'LI', 'Andorra' => 'AD',
        "Stati Uniti d'America" => 'US', 'Stati Uniti' => 'US', 'United States' => 'US', 'Canada' => 'CA', 'Messico' => 'MX', 'México' => 'MX',
        'Cuba' => 'CU', 'Brasile' => 'BR', 'Argentina' => 'AR', 'Perù' => 'PE', 'Cile' => 'CL', 'Marocco' => 'MA', 'Tunisia' => 'TN', 'Egitto' => 'EG',
        'Capo Verde' => 'CV', 'Kenya' => 'KE', 'Tanzania' => 'TZ', 'Sudafrica' => 'ZA', 'Israele' => 'IL', 'Giordania' => 'JO',
        'Emirati Arabi Uniti' => 'AE', 'India' => 'IN', 'Sri Lanka' => 'LK', 'Maldive' => 'MV', 'Thailandia' => 'TH', 'Vietnam' => 'VN',
        'Indonesia' => 'ID', 'Cina' => 'CN', 'Giappone' => 'JP', '日本' => 'JP', 'Corea del Sud' => 'KR', 'Australia' => 'AU', 'Nuova Zelanda' => 'NZ',
    ];

    // Years that can be opened: past years with albums, and the current one from December (admins: always)
    public static function years(User $user): array
    {
        return $user->listing()->selectRaw('YEAR(dateFrom) as year')->distinct()->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->filter(fn ($year) => self::isOpen($user, $year))
            ->sortDesc()->values()->all();
    }

    public static function isOpen(User $user, int $year): bool
    {
        $now = now();

        return $year < $now->year || ($year === $now->year && ($now->month >= self::OPENS_MONTH || $user->isAdmin == 1));
    }

    // Everything the slides show, or null without albums that year
    public static function for(User $user, int $year): ?array
    {
        $albums = $user->listing()->with(['places', 'users'])
            ->whereYear('dateFrom', $year)
            ->orderBy('dateFrom')
            ->get();
        if ($albums->isEmpty()) {
            return null;
        }

        $home = $user->home();

        // Carbon 3 gives decimals: whole days, the first and last included
        $days = fn (Listing $album) => (int) round(Carbon::parse($album->dateFrom)->startOfDay()->diffInDays(Carbon::parse($album->dateTo)->startOfDay())) + 1;
        $places = $albums->flatMap(fn (Listing $album) => $album->places->map(fn ($place) => [
            'album' => $album->title,
            'name' => trim(Str::before((string) $place->place_name, ',')) ?: $album->location,
            'country' => trim((string) Str::afterLast((string) $place->place_name, ',')),
            'km' => self::distance($home['point'], [(float) $place->latitude, (float) $place->longitude]),
            'elevation' => $place->elevation,
        ]));

        $countries = $places->pluck('country')->filter()->unique()->values()->map(fn ($name) => [
            'name' => $name,
            'flag' => self::flag(self::COUNTRIES[$name] ?? null),
        ]);
        $farthest = $places->sortByDesc('km')->first();
        // Only worth a slide from 500 m up
        $highest = $places->whereNotNull('elevation')->sortByDesc('elevation')->first();
        $highest = $highest && $highest['elevation'] >= 500 ? $highest : null;
        $longest = $albums->sortByDesc($days)->first();
        $months = $albums->groupBy(fn (Listing $album) => Carbon::parse($album->dateFrom)->month)->map->count()->sortDesc();

        // Friends: who was in the same albums, most first (not the demo account)
        $friends = $albums->flatMap(fn (Listing $album) => $album->users)
            ->reject(fn (User $person) => $person->id === $user->id || $person->is_demo)
            ->groupBy('id')
            ->map(fn (Collection $times) => ['name' => $times->first()->name, 'avatar' => Media::url($times->first()->profilePicture), 'trips' => $times->count()])
            ->sortByDesc('trips')->take(3)->values();

        $previous = $user->listing()->whereYear('dateFrom', $year - 1)->count();

        return [
            'year' => $year,
            'name' => $user->name,
            'albums' => $albums->map(fn (Listing $album) => [
                'id' => $album->id,
                'title' => $album->title,
                'cover' => Media::url($album->logo),
                'dateFrom' => $album->dateFrom,
            ])->values(),
            'albumCount' => $albums->count(),
            'previousYear' => $previous,
            'daysAway' => $albums->sum($days),
            'km' => (int) round($albums->sum(fn (Listing $album) => self::tripKm($album, $home['point']))),
            'home' => $home['name'],
            'countries' => $countries,
            'placeCount' => $places->pluck('name')->unique()->count(),
            'farthest' => $farthest ? ['name' => $farthest['name'], 'album' => $farthest['album'], 'km' => (int) round($farthest['km'])] : null,
            'highest' => $highest ? ['name' => $highest['name'], 'album' => $highest['album'], 'meters' => $highest['elevation']] : null,
            'longest' => ['title' => $longest->title, 'days' => $days($longest)],
            'busiestMonth' => $months->isNotEmpty() ? ['name' => ucfirst(Carbon::create($year, $months->keys()->first())->translatedFormat('F')), 'albums' => $months->first()] : null,
            'friends' => $friends,
            'comments' => self::comments($user, $year),
        ];
    }

    // Comments written that year and the most liked one
    private static function comments(User $user, int $year): array
    {
        $album = Comment::with('listing')->withCount('likes')->where('user_id', $user->id)->whereYear('created_at', $year)->get();
        $forum = PostComment::with('post')->withCount('likes')->where('user_id', $user->id)->whereYear('created_at', $year)->get();
        $best = $album->concat($forum)->filter(fn ($comment) => $comment->likes_count > 0)
            ->sortBy([['likes_count', 'desc'], ['created_at', 'desc']])->first();

        return [
            'written' => $album->count() + $forum->count(),
            'likesReceived' => $album->sum('likes_count') + $forum->sum('likes_count'),
            'best' => $best ? [
                'text' => Str::limit(trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], ' ', $best->comment)))), 160),
                'likes' => $best->likes_count,
                'on' => $best instanceof Comment ? $best->listing?->title : $best->post?->title,
            ] : null,
        ];
    }

    // From home to each place of the album (in their order) and back
    private static function tripKm(Listing $album, array $home): float
    {
        $points = $album->places->sortBy('position')->map(fn ($place) => [(float) $place->latitude, (float) $place->longitude])->values();
        if ($points->isEmpty()) {
            return 0;
        }
        $route = collect([$home])->concat($points)->push($home);

        return $route->sliding(2)->sum(fn ($pair) => self::distance($pair->first(), $pair->last()));
    }

    public static function distance(array $from, array $to): float
    {
        [$lat1, $lon1] = array_map('deg2rad', $from);
        [$lat2, $lon2] = array_map('deg2rad', $to);
        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }

    // "IT" → 🇮🇹
    private static function flag(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
