<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Mentioned;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// "@Anna B" in a comment: the comment box suggests the people who can see the album or post and
// writes their full name; here the names are found again among those same people (longest first,
// so "@Anna B" isn't taken for "@Anna"), and they're told
class Mentions
{
    // The people of $candidates written as @Name in $text
    public static function find(string $text, Collection $candidates): Collection
    {
        $found = collect();
        foreach ($candidates->sortByDesc(fn (User $user) => mb_strlen($user->name)) as $user) {
            $pattern = '/(^|[^\pL\pN_])@' . preg_quote($user->name, '/') . '(?![\pL\pN_])/iu';
            if (preg_match($pattern, $text)) {
                $found->push($user);
                // So a shorter name inside this one isn't found too
                $text = preg_replace($pattern, '$1', $text);
            }
        }

        return $found;
    }

    // The same names in bold, in a comment already turned into safe HTML
    public static function bold(string $html, Collection $users): string
    {
        foreach ($users->sortByDesc(fn (User $user) => mb_strlen($user->name)) as $user) {
            $name = e($user->name);
            // (not twice: a longer name already in bold is skipped)
            $html = preg_replace('/(^|[^\pL\pN_])(?<!<strong>)@' . preg_quote($name, '/') . '(?![\pL\pN_])/iu', '$1<strong>@' . $name . '</strong>', $html);
        }

        return $html;
    }

    // Bell and phone ("Muto mentioned you"), never to who wrote it or the demo account
    public static function notify(Collection $users, User $author, string $text, string $title, string $url): void
    {
        $users = $users->reject(fn (User $user) => $user->id === $author->id || $user->is_demo);
        $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '</p>'], ' ', $text)))), 80);
        foreach ($users as $user) {
            $user->notify(new Mentioned($author, $excerpt, $title, $url));
        }
        Notify::push($users, 'mention', fn () => [
            'title' => __(':name mentioned you', ['name' => $author->name]),
            'body' => "{$title}: \"{$excerpt}\"",
            'url' => $url,
            'tag' => 'mention-' . md5($url),
        ]);
    }
}
