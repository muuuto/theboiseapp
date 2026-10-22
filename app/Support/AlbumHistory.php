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
        $who = $event->user?->name ?? __('Someone');
        $album = $event->listing_title ? '"' . $event->listing_title . '"' : __('an album');
        $details = $event->details ?? [];
        $names = fn (string $key) => collect($details[$key] ?? [])->join(', ', ' ' . __('and') . ' ');
        $vars = ['who' => $who, 'album' => $album, 'user' => $details['user'] ?? __('someone')];

        $summary = match ($event->action) {
            'created' => isset($details['people'])
                ? __(':who posted :album with :people', $vars + ['people' => $names('people')])
                : __(':who posted :album', $vars),
            'updated' => __(':who edited :album', $vars),
            'deleted' => __(':who deleted :album', $vars),
            'permission_granted' => __(':who allowed :user to post albums', $vars),
            'permission_revoked' => __(':who stopped :user from posting albums', $vars),
            'permission_everyone' => __(':who allowed everyone to post albums', $vars),
            'permission_no_one' => __(':who stopped everyone (but admins) from posting albums', $vars),
            'posting_on' => __(':who switched on album posting for members', $vars),
            'posting_off' => __(':who switched off album posting for members', $vars),
            default => "{$who}: {$event->action}",
        };

        // The field names are saved in English: translated when shown
        $lines = collect($details['changes'] ?? [])->map(fn ($change) => is_null($change['from']) && is_null($change['to'])
            ? __(':field changed', ['field' => __($change['field'])])
            : __($change['field']) . ": \"{$change['from']}\" → \"{$change['to']}\"");
        if (! empty($details['added'])) {
            $lines->push(__('added :names', ['names' => $names('added')]));
        }
        if (! empty($details['removed'])) {
            $lines->push(__('removed :names', ['names' => $names('removed')]));
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
