<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// The album tags library: finding a tag from what someone typed (its name or an alias), tags that
// look alike ("Matte" next to "Matteo"), and renaming a tag on every album that has it
class Tags
{
    // "Sarà " → "sara": no case, accents or extra spaces
    public static function plain(string $text): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($text))));
    }

    // The tag someone means: its exact name first, then any spelling of its name or aliases
    public static function resolve(string $typed, Collection $library): ?Tag
    {
        $plain = self::plain($typed);

        return $library->first(fn (Tag $tag) => $tag->name === trim($typed))
            ?? $library->first(fn (Tag $tag) => self::plain($tag->name) === $plain)
            ?? $library->first(fn (Tag $tag) => collect($tag->aliases ?? [])->contains(fn ($alias) => self::plain($alias) === $plain));
    }

    // Tags that look like $typed: one starts with the other ("Mat" / "Matteo") or a letter or two
    // apart ("Sarà" / "Sara", "Mirko" / "Mikko")
    public static function similar(string $typed, Collection $library): Collection
    {
        $plain = self::plain($typed);

        return $library->filter(function (Tag $tag) use ($plain) {
            foreach ([$tag->name, ...($tag->aliases ?? [])] as $spelling) {
                $other = self::plain($spelling);
                // The same once accents and case are gone ("Sarà" / "Sara"): the most alike
                if ($other === $plain) {
                    return true;
                }
                $shorter = min(strlen($other), strlen($plain));
                if ($shorter >= 3 && (str_starts_with($other, $plain) || str_starts_with($plain, $other))) {
                    return true;
                }
                if (levenshtein($other, $plain) <= (max(strlen($other), strlen($plain)) >= 6 ? 2 : 1)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    // Pairs of library tags that may be the same person, for the admin page
    public static function lookAlikes(Collection $library): Collection
    {
        $pairs = collect();
        foreach ($library as $tag) {
            foreach (self::similar($tag->name, $library) as $other) {
                if ($tag->id < $other->id) {
                    $pairs->push([$tag, $other]);
                }
            }
        }

        return $pairs;
    }

    // On every album: $old becomes $new (or goes away when null), without doubles
    public static function replaceInAlbums(string $old, ?string $new): int
    {
        $changed = 0;
        Listing::where('tags', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $old) . '%')->get()->each(function (Listing $listing) use ($old, $new, &$changed) {
            $tags = array_values(array_filter(array_map('trim', explode(',', (string) $listing->tags))));
            if (! in_array($old, $tags, true)) {
                return;
            }
            $tags = collect($tags)->map(fn ($tag) => $tag === $old ? $new : $tag)->filter()->unique()->values();
            $listing->update(['tags' => $tags->implode(', ')]);
            $changed++;
        });

        return $changed;
    }

    // How many albums have each tag: [name => count]
    public static function counts(): Collection
    {
        return Listing::pluck('tags')
            ->flatMap(fn ($tags) => array_filter(array_map('trim', explode(',', (string) $tags))))
            ->countBy();
    }
}
