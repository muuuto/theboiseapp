<?php

namespace App\Support;

use App\Models\AlbumEvent;
use App\Models\Listing;
use Illuminate\Support\Str;

// The album history in words, for the admins: "Zecca edited Sicilia 2026: title, dates"
class AlbumHistory
{
    private const LABELS = [
        'title' => 'title',
        'dateFrom' => 'start date',
        'dateTo' => 'end date',
        'location' => 'location',
        'albumLink' => 'photos link',
        'tags' => 'tags',
        'description' => 'description',
        'videoLinks' => 'videos',
        'cover' => 'preview photo',
        'places' => 'map places',
    ];

    // What an album looks like, to compare before and after an edit
    public static function snapshot(Listing $listing): array
    {
        $listing->load('places');

        return [
            'title' => $listing->title,
            'dateFrom' => $listing->dateFrom,
            'dateTo' => $listing->dateTo,
            'location' => $listing->location,
            'albumLink' => $listing->albumLink,
            'tags' => $listing->tags,
            'description' => $listing->description,
            'videoLinks' => $listing->videoLinks,
            'cover' => $listing->logo,
            'places' => $listing->places->pluck('place_name')->filter()->implode(' · '),
        ];
    }

    // [['field' => 'title', 'from' => ..., 'to' => ...]]: long texts shortened, the preview only "changed"
    public static function changes(array $before, array $after): array
    {
        $changes = [];
        foreach (self::LABELS as $field => $label) {
            if ((string) ($before[$field] ?? '') === (string) ($after[$field] ?? '')) {
                continue;
            }
            $changes[] = $field === 'cover'
                ? ['field' => $label, 'from' => null, 'to' => null]
                : ['field' => $label, 'from' => self::short($before[$field] ?? ''), 'to' => self::short($after[$field] ?? '')];
        }

        return $changes;
    }

    public static function describe(AlbumEvent $event): array
    {
        $who = $event->user?->name ?? 'Someone';
        $album = $event->listing_title ? '"' . $event->listing_title . '"' : 'an album';
        $details = $event->details ?? [];
        $names = fn (string $key) => collect($details[$key] ?? [])->join(', ', ' and ');

        $summary = match ($event->action) {
            'created' => "{$who} posted {$album}" . (isset($details['people']) ? ' with ' . $names('people') : ''),
            'updated' => "{$who} edited {$album}",
            'deleted' => "{$who} deleted {$album}",
            'permission_granted' => "{$who} allowed " . ($details['user'] ?? 'someone') . ' to post albums',
            'permission_revoked' => "{$who} stopped " . ($details['user'] ?? 'someone') . ' from posting albums',
            'permission_everyone' => "{$who} allowed everyone to post albums",
            'permission_no_one' => "{$who} stopped everyone (but admins) from posting albums",
            'posting_on' => "{$who} switched on album posting for members",
            'posting_off' => "{$who} switched off album posting for members",
            default => "{$who}: {$event->action}",
        };

        $lines = collect($details['changes'] ?? [])->map(fn ($change) => is_null($change['from']) && is_null($change['to'])
            ? "{$change['field']} changed"
            : "{$change['field']}: \"{$change['from']}\" → \"{$change['to']}\"");
        if (! empty($details['added'])) {
            $lines->push('added ' . $names('added'));
        }
        if (! empty($details['removed'])) {
            $lines->push('removed ' . $names('removed'));
        }

        return [
            'id' => $event->id,
            'albumId' => $event->action === 'deleted' ? null : $event->listing_id,
            'summary' => $summary,
            'lines' => $lines->values(),
            'at' => $event->created_at?->toIso8601String(),
        ];
    }

    private static function short(?string $text): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))), 80);
    }
}
